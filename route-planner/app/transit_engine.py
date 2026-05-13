from __future__ import annotations

import sys
from collections.abc import Mapping, Sequence
from functools import cmp_to_key
from dataclasses import dataclass
from datetime import datetime
from typing import Any

import psycopg2
import psycopg2.extensions
from psycopg2.extras import RealDictCursor

from app import gtfs_time as gt

MIN_TRANSFER_SECONDS = 180
MAX_TRANSFER_SECONDS = 900
NEAR_DEPARTURE_SECONDS = 120
SEARCH_WINDOW_SECONDS = 10800
SEARCH_WINDOW_FALLBACK_SECONDS = 28800
TRANSFER_RANKING_PENALTY_SECONDS = 900
DIRECT_LEGS_SQL_LIMIT = 200
ONE_TRANSFER_ROW_LIMIT = 2500


def normalize_stop_ids(stop_ids: int | Sequence[int]) -> list[int]:
    if isinstance(stop_ids, int):
        ids = [stop_ids]
    else:
        ids = list(stop_ids)
    out: list[int] = []
    seen: set[int] = set()
    for x in ids:
        i = int(x)
        if i not in seen:
            seen.add(i)
            out.append(i)
    return out


def leg_payload(leg: dict[str, Any]) -> dict[str, Any]:
    return {
        "trip_id": leg["trip_id"],
        "trip_pk": leg["trip_pk"],
        "from_stop_id": int(leg["from_stop_id"]),
        "to_stop_id": int(leg["to_stop_id"]),
        "route": leg["route"],
        "from_departure_time": leg["from_departure_time"],
        "to_arrival_time": leg["to_arrival_time"],
        "stops_span": leg["stops_span"],
    }


def format_transit_path(legs: list[dict[str, Any]]) -> dict[str, Any]:
    if len(legs) == 1:
        leg = leg_payload(legs[0])
        return {
            "type": "direct",
            "trip_id": leg["trip_id"],
            "trip_pk": leg["trip_pk"],
            "from_stop_id": leg["from_stop_id"],
            "to_stop_id": leg["to_stop_id"],
            "route": leg["route"],
            "from_departure_time": leg["from_departure_time"],
            "to_arrival_time": leg["to_arrival_time"],
            "stops_span": leg["stops_span"],
        }
    if len(legs) == 2:
        return {
            "type": "one_transfer",
            "transfer_stop_id": int(legs[0]["to_stop_id"]),
            "legs": [leg_payload(x) for x in legs],
        }
    return {
        "type": "multi_transfer",
        "transfer_stop_ids": [int(x["to_stop_id"]) for x in legs[:-1]],
        "legs": [leg_payload(x) for x in legs],
    }


def path_signature(legs: list[dict[str, Any]]) -> str:
    lines = "->".join(x["route"]["short_name"] for x in legs)
    return f"{lines}:{legs[0]['from_departure_time']}"


def path_duration_seconds(legs: list[dict[str, Any]]) -> int:
    start = gt.gtfs_time_to_seconds(str(legs[0]["from_departure_time"]))
    end = gt.gtfs_time_to_seconds(str(legs[-1]["to_arrival_time"]))
    if end < start:
        end += 86400
    return end - start


def path_arrival_seconds(legs: list[dict[str, Any]]) -> int:
    start = gt.gtfs_time_to_seconds(str(legs[0]["from_departure_time"]))
    end = gt.gtfs_time_to_seconds(str(legs[-1]["to_arrival_time"]))
    if end < start:
        end += 86400
    return end


def path_ranking_score(legs: list[dict[str, Any]]) -> int:
    return path_arrival_seconds(legs) + TRANSFER_RANKING_PENALTY_SECONDS * max(0, len(legs) - 1)


def path_destination_rank(legs: list[dict[str, Any]], egress: dict[int, int] | None) -> int:
    if egress is None:
        return 0
    last = int(legs[-1]["to_stop_id"])
    return egress.get(last, sys.maxsize)


def compare_candidates(
    left: list[dict[str, Any]], right: list[dict[str, Any]], egress: dict[int, int] | None
) -> int:
    ls = path_ranking_score(left)
    rs = path_ranking_score(right)
    if ls != rs:
        return -1 if ls < rs else 1
    la = path_arrival_seconds(left)
    ra = path_arrival_seconds(right)
    if la != ra:
        return -1 if la < ra else 1
    lt = len(left) - 1
    rt = len(right) - 1
    if lt != rt:
        return -1 if lt < rt else 1
    ldr = path_destination_rank(left, egress)
    rdr = path_destination_rank(right, egress)
    if ldr != rdr:
        return -1 if ldr < rdr else 1
    ld = path_duration_seconds(left)
    rd = path_duration_seconds(right)
    return -1 if ld < rd else (1 if ld > rd else 0)


def is_dominated_by_direct_leg(
    transfer_legs: list[dict[str, Any]], direct_paths: list[list[dict[str, Any]]], egress: dict[int, int] | None
) -> bool:
    first_leg = transfer_legs[0]
    last_leg = transfer_legs[-1]
    transfer_arrival = path_arrival_seconds(transfer_legs)
    transfer_destination_rank = path_destination_rank(transfer_legs, egress)
    first_route = str(first_leg["route"]["short_name"]).strip()
    last_route = str(last_leg["route"]["short_name"]).strip()
    routes_to_check = list(dict.fromkeys([first_route, last_route]))
    for direct_legs in direct_paths:
        direct_leg = direct_legs[0]
        if str(direct_leg["route"]["short_name"]).strip() not in routes_to_check:
            continue
        if int(direct_leg["from_stop_id"]) != int(first_leg["from_stop_id"]):
            continue
        direct_arrival = path_arrival_seconds(direct_legs)
        if direct_arrival > transfer_arrival + 120:
            continue
        direct_destination_rank = path_destination_rank(direct_legs, egress)
        if egress is not None:
            if direct_destination_rank <= transfer_destination_rank:
                return True
            continue
        if int(direct_leg["to_stop_id"]) == int(last_leg["to_stop_id"]):
            return True
    return False


def remove_dominated_transfer_paths(paths: list[list[dict[str, Any]]], egress: dict[int, int] | None) -> list[list[dict[str, Any]]]:
    direct_paths = [p for p in paths if len(p) == 1]
    return [p for p in paths if len(p) < 2 or not is_dominated_by_direct_leg(p, direct_paths, egress)]


def collapse_near_departures_for_pattern(
    paths: list[list[dict[str, Any]]], egress: dict[int, int] | None
) -> list[list[dict[str, Any]]]:
    if not paths:
        return []
    grouped: dict[str, list[list[dict[str, Any]]]] = {}
    for legs in paths:
        pat = "->".join(x["route"]["short_name"] for x in legs)
        grouped.setdefault(pat, []).append(legs)
    collapsed: list[list[dict[str, Any]]] = []
    for pattern_paths in grouped.values():
        pattern_paths.sort(
            key=lambda legs: gt.gtfs_time_to_seconds(str(legs[0]["from_departure_time"]))
        )
        last_kept: int | None = None
        for legs in pattern_paths:
            dep = gt.gtfs_time_to_seconds(str(legs[0]["from_departure_time"]))
            if last_kept is not None and dep - last_kept < NEAR_DEPARTURE_SECONDS:
                continue
            collapsed.append(legs)
            last_kept = dep
    collapsed.sort(
        key=cmp_to_key(lambda a, b: compare_candidates(a, b, egress)),
    )
    return collapsed


def select_top_candidates(candidate_paths: list[list[dict[str, Any]]], limit: int, egress: dict[int, int] | None) -> list[list[dict[str, Any]]]:
    if not candidate_paths:
        return []
    candidate_paths = sorted(
        candidate_paths,
        key=cmp_to_key(lambda a, b: compare_candidates(a, b, egress)),
    )
    selected: list[list[dict[str, Any]]] = []
    selected_sigs: set[str] = set()
    for legs in candidate_paths:
        if len(selected) >= limit:
            break
        sig = path_signature(legs)
        if sig in selected_sigs:
            continue
        selected_sigs.add(sig)
        selected.append(legs)
    return selected


def remember_candidate(candidates: dict[str, list[dict[str, Any]]], legs: list[dict[str, Any]]) -> None:
    sig = path_signature(legs)
    if sig not in candidates:
        candidates[sig] = legs


def remember_pattern_schedule(results: list[list[dict[str, Any]]], legs: list[dict[str, Any]]) -> bool:
    sig = path_signature(legs)
    return any(path_signature(existing) == sig for existing in results)


def passes_transfer_gap(later_time: str, earlier_time: str) -> bool:
    later = gt.gtfs_time_to_seconds(later_time)
    earlier = gt.gtfs_time_to_seconds(earlier_time)
    if later < earlier:
        later += 86400
    gap = later - earlier
    return MIN_TRANSFER_SECONDS <= gap <= MAX_TRANSFER_SECONDS


def make_leg_from_row(row: Mapping[str, Any]) -> dict[str, Any]:
    return {
        "trip_id": str(row["trip_id"]),
        "trip_pk": int(row["trip_pk"]),
        "from_stop_id": int(row["from_stop_id"]),
        "to_stop_id": int(row["to_stop_id"]),
        "route": {
            "id": int(row["route_pk"]),
            "route_id": str(row["route_id"]),
            "short_name": str(row["route_short_name"]).strip(),
            "long_name": str(row["route_long_name"]) if row.get("route_long_name") is not None else None,
        },
        "from_departure_time": str(row["from_departure_time"]),
        "to_arrival_time": str(row["to_arrival_time"]),
        "stops_span": int(row["to_sequence"]) - int(row["from_sequence"]),
    }


def make_two_legs_from_transfer_row(row: Mapping[str, Any]) -> list[dict[str, Any]]:
    return [
        {
            "trip_id": str(row["trip1_id"]),
            "trip_pk": int(row["trip1_pk"]),
            "from_stop_id": int(row["from_stop_id"]),
            "to_stop_id": int(row["transfer_stop_id"]),
            "route": {
                "id": int(row["route1_pk"]),
                "route_id": str(row["route1_id"]),
                "short_name": str(row["route1_short_name"]).strip(),
                "long_name": str(row["route1_long_name"]) if row.get("route1_long_name") is not None else None,
            },
            "from_departure_time": str(row["leg1_departure_time"]),
            "to_arrival_time": str(row["leg1_arrival_time"]),
            "stops_span": int(row["leg1_to_sequence"]) - int(row["leg1_from_sequence"]),
        },
        {
            "trip_id": str(row["trip2_id"]),
            "trip_pk": int(row["trip2_pk"]),
            "from_stop_id": int(row["transfer_stop_id"]),
            "to_stop_id": int(row["to_stop_id"]),
            "route": {
                "id": int(row["route2_pk"]),
                "route_id": str(row["route2_id"]),
                "short_name": str(row["route2_short_name"]).strip(),
                "long_name": str(row["route2_long_name"]) if row.get("route2_long_name") is not None else None,
            },
            "from_departure_time": str(row["leg2_departure_time"]),
            "to_arrival_time": str(row["leg2_arrival_time"]),
            "stops_span": int(row["leg2_to_sequence"]) - int(row["leg2_from_sequence"]),
        },
    ]


def is_accessible_departure(
    first_leg: dict[str, Any],
    from_stop_ids: list[int],
    access_seconds_by_stop: dict[int, int] | None,
    default_access_seconds: int,
) -> bool:
    if int(first_leg["from_stop_id"]) not in from_stop_ids:
        return False
    dep = gt.gtfs_time_to_seconds(str(first_leg["from_departure_time"]))
    req = access_seconds_by_stop.get(int(first_leg["from_stop_id"]), default_access_seconds) if access_seconds_by_stop else default_access_seconds
    return dep >= req


@dataclass
class PlanContext:
    cur: psycopg2.extensions.cursor


def apply_accessible_departure_sql(
    from_stop_ids: list[int],
    access_seconds_by_stop: dict[int, int] | None,
    default_access_seconds: int,
    stop_column: str,
    departure_column: str,
) -> tuple[str, list[Any]]:
    if not access_seconds_by_stop:
        return "", []
    parts: list[str] = []
    params: list[Any] = []
    for sid in from_stop_ids:
        req = access_seconds_by_stop.get(sid, default_access_seconds)
        parts.append(f"({stop_column} = %s AND {departure_column} >= %s)")
        params.extend([sid, gt.seconds_to_gtfs_time(req)])
    return "(" + " OR ".join(parts) + ")", params


def collect_direct_legs(
    ctx: PlanContext,
    from_stop_ids: list[int],
    to_stop_ids: list[int],
    earliest_departure: str,
    latest_departure: str,
    active_services: list[str],
    access_seconds_by_stop: dict[int, int] | None,
    default_access_seconds: int,
) -> list[list[dict[str, Any]]]:
    extra_sql, extra_params = apply_accessible_departure_sql(
        from_stop_ids, access_seconds_by_stop, default_access_seconds, "from_st.stop_id", "from_st.departure_time"
    )
    where_extra = f" AND {extra_sql}" if extra_sql else ""
    sql = f"""
        SELECT t.id AS trip_pk, t.trip_id, r.id AS route_pk, r.route_id, r.route_short_name, r.route_long_name,
               from_st.stop_id AS from_stop_id, to_st.stop_id AS to_stop_id,
               from_st.departure_time AS from_departure_time, to_st.arrival_time AS to_arrival_time,
               from_st.stop_sequence AS from_sequence, to_st.stop_sequence AS to_sequence
        FROM gtfs_stop_times AS from_st
        JOIN gtfs_stop_times AS to_st ON to_st.trip_id = from_st.trip_id
        JOIN gtfs_trips AS t ON t.id = from_st.trip_id
        JOIN gtfs_routes AS r ON r.id = t.route_id
        WHERE from_st.stop_id = ANY(%s) AND to_st.stop_id = ANY(%s)
          AND t.service_id = ANY(%s)
          AND from_st.stop_sequence < to_st.stop_sequence
          AND from_st.departure_time >= %s AND from_st.departure_time <= %s
          {where_extra}
        ORDER BY from_st.departure_time
        LIMIT {DIRECT_LEGS_SQL_LIMIT}
    """
    params: list[Any] = [from_stop_ids, to_stop_ids, active_services, earliest_departure, latest_departure] + extra_params
    ctx.cur.execute(sql, params)
    rows = ctx.cur.fetchall()
    results: list[list[dict[str, Any]]] = []
    for row in rows:
        legs = [make_leg_from_row(row)]
        if not is_accessible_departure(legs[0], from_stop_ids, access_seconds_by_stop, default_access_seconds):
            continue
        if remember_pattern_schedule(results, legs):
            continue
        results.append(legs)
    return results


def collect_one_transfer_legs(
    ctx: PlanContext,
    from_stop_ids: list[int],
    to_stop_ids: list[int],
    earliest_departure: str,
    latest_departure: str,
    active_services: list[str],
    access_seconds_by_stop: dict[int, int] | None,
    default_access_seconds: int,
) -> list[list[dict[str, Any]]]:
    extra_sql, extra_params = apply_accessible_departure_sql(
        from_stop_ids, access_seconds_by_stop, default_access_seconds, "a.stop_id", "a.departure_time"
    )
    where_extra = f" AND {extra_sql}" if extra_sql else ""
    sql = f"""
        SELECT
            t1.id AS trip1_pk, t1.trip_id AS trip1_id,
            t2.id AS trip2_pk, t2.trip_id AS trip2_id,
            r1.id AS route1_pk, r1.route_id AS route1_id,
            r1.route_short_name AS route1_short_name, r1.route_long_name AS route1_long_name,
            r2.id AS route2_pk, r2.route_id AS route2_id,
            r2.route_short_name AS route2_short_name, r2.route_long_name AS route2_long_name,
            a.stop_id AS from_stop_id, b.stop_id AS transfer_stop_id, d.stop_id AS to_stop_id,
            a.departure_time AS leg1_departure_time, b.arrival_time AS leg1_arrival_time,
            c.departure_time AS leg2_departure_time, d.arrival_time AS leg2_arrival_time,
            a.stop_sequence AS leg1_from_sequence, b.stop_sequence AS leg1_to_sequence,
            c.stop_sequence AS leg2_from_sequence, d.stop_sequence AS leg2_to_sequence
        FROM gtfs_stop_times AS a
        JOIN gtfs_stop_times AS b ON b.trip_id = a.trip_id
        JOIN gtfs_stop_times AS c ON c.stop_id = b.stop_id
        JOIN gtfs_stop_times AS d ON d.trip_id = c.trip_id
        JOIN gtfs_trips AS t1 ON t1.id = a.trip_id
        JOIN gtfs_trips AS t2 ON t2.id = c.trip_id
        JOIN gtfs_routes AS r1 ON r1.id = t1.route_id
        JOIN gtfs_routes AS r2 ON r2.id = t2.route_id
        WHERE a.stop_id = ANY(%s) AND d.stop_id = ANY(%s)
          AND t1.service_id = ANY(%s) AND t2.service_id = ANY(%s)
          AND a.stop_sequence < b.stop_sequence AND c.stop_sequence < d.stop_sequence
          AND a.trip_id <> c.trip_id AND c.departure_time >= b.arrival_time
          AND a.departure_time >= %s AND a.departure_time <= %s
          {where_extra}
        ORDER BY a.departure_time
        LIMIT {ONE_TRANSFER_ROW_LIMIT}
    """
    params: list[Any] = [
        from_stop_ids,
        to_stop_ids,
        active_services,
        active_services,
        earliest_departure,
        latest_departure,
    ] + extra_params
    ctx.cur.execute(sql, params)
    rows = ctx.cur.fetchall()
    results: list[list[dict[str, Any]]] = []
    for row in rows:
        if not passes_transfer_gap(str(row["leg2_departure_time"]), str(row["leg1_arrival_time"])):
            continue
        legs = make_two_legs_from_transfer_row(row)
        if not is_accessible_departure(legs[0], from_stop_ids, access_seconds_by_stop, default_access_seconds):
            continue
        if remember_pattern_schedule(results, legs):
            continue
        results.append(legs)
    return results


def build_connections(
    ctx: PlanContext, earliest_seconds: int, window_end_seconds: int, active_services: list[str]
) -> list[dict[str, Any]]:
    sql = """
        SELECT st.trip_id, st.stop_id, st.stop_sequence, st.departure_time, st.arrival_time,
               t.id AS trip_pk, t.trip_id AS trip_code,
               r.id AS route_pk, r.route_id, r.route_short_name, r.route_long_name
        FROM gtfs_stop_times AS st
        JOIN gtfs_trips AS t ON t.id = st.trip_id
        JOIN gtfs_routes AS r ON r.id = t.route_id
        WHERE t.service_id = ANY(%s)
          AND st.departure_time >= %s AND st.departure_time <= %s
        ORDER BY st.trip_id, st.stop_sequence
        LIMIT 200000
    """
    ctx.cur.execute(
        sql,
        (
            active_services,
            gt.seconds_to_gtfs_time(max(0, earliest_seconds - 300)),
            gt.seconds_to_gtfs_time(window_end_seconds),
        ),
    )
    rows = ctx.cur.fetchall()
    connections: list[dict[str, Any]] = []
    current_trip_id: int | None = None
    previous: Mapping[str, Any] | None = None
    for row in rows:
        tid = int(row["trip_id"])
        if current_trip_id != tid:
            current_trip_id = tid
            previous = None
        if previous is not None:
            connections.append(
                {
                    "dep_stop_id": int(previous["stop_id"]),
                    "arr_stop_id": int(row["stop_id"]),
                    "dep_seconds": gt.gtfs_time_to_seconds(str(previous["departure_time"])),
                    "arr_seconds": gt.gtfs_time_to_seconds(str(row["arrival_time"])),
                    "from_sequence": int(previous["stop_sequence"]),
                    "to_sequence": int(row["stop_sequence"]),
                    "trip_pk": int(row["trip_pk"]),
                    "trip_id": str(row["trip_code"]),
                    "route": {
                        "id": int(row["route_pk"]),
                        "route_id": str(row["route_id"]),
                        "short_name": str(row["route_short_name"]).strip(),
                        "long_name": str(row["route_long_name"]) if row.get("route_long_name") is not None else None,
                    },
                }
            )
        previous = row
    connections.sort(key=lambda c: c["dep_seconds"])
    return connections


def connections_to_legs(trail: list[dict[str, Any]]) -> list[dict[str, Any]]:
    legs: list[dict[str, Any]] = []
    index = 0
    while index < len(trail):
        trip_pk = trail[index]["trip_pk"]
        end = index
        while end < len(trail) and trail[end]["trip_pk"] == trip_pk:
            end += 1
        first = trail[index]
        last = trail[end - 1]
        legs.append(
            {
                "trip_id": first["trip_id"],
                "trip_pk": trip_pk,
                "from_stop_id": first["dep_stop_id"],
                "to_stop_id": last["arr_stop_id"],
                "route": first["route"],
                "from_departure_time": gt.seconds_to_gtfs_time(first["dep_seconds"]),
                "to_arrival_time": gt.seconds_to_gtfs_time(last["arr_seconds"]),
                "stops_span": last["to_sequence"] - first["from_sequence"],
            }
        )
        index = end
    return legs


def build_trail(connection: dict[str, Any], previous_connection: dict[int, dict[str, Any] | None], dep_stop_id: int) -> list[dict[str, Any]]:
    trail = [connection]
    current_stop_id = dep_stop_id
    while previous_connection.get(current_stop_id):
        previous = previous_connection[current_stop_id]
        assert previous is not None
        trail.append(previous)
        current_stop_id = previous["dep_stop_id"]
    trail.reverse()
    return trail


def collect_two_transfer_legs(
    ctx: PlanContext,
    from_stop_ids: list[int],
    to_stop_ids: list[int],
    window_start_seconds: int,
    window_end_seconds: int,
    active_services: list[str],
) -> list[list[dict[str, Any]]]:
    connections = build_connections(ctx, window_start_seconds, window_end_seconds, active_services)
    if not connections:
        return []
    earliest_arrival: dict[int, int] = {}
    previous_connection: dict[int, dict[str, Any] | None] = {}
    transfer_count: dict[int, int] = {}
    for sid in from_stop_ids:
        earliest_arrival[sid] = window_start_seconds
        previous_connection[sid] = None
        transfer_count[sid] = -1
    results: list[list[dict[str, Any]]] = []
    for connection in connections:
        dep_stop_id = connection["dep_stop_id"]
        if dep_stop_id not in earliest_arrival:
            continue
        required_departure = earliest_arrival[dep_stop_id]
        previous = previous_connection[dep_stop_id]
        if previous is not None and previous["trip_pk"] != connection["trip_pk"]:
            transfer_gap = connection["dep_seconds"] - previous["arr_seconds"]
            if transfer_gap < MIN_TRANSFER_SECONDS or transfer_gap > MAX_TRANSFER_SECONDS:
                continue
            required_departure = previous["arr_seconds"] + MIN_TRANSFER_SECONDS
        if connection["dep_seconds"] < required_departure:
            continue
        arr_stop_id = connection["arr_stop_id"]
        next_transfers = (
            transfer_count[dep_stop_id]
            if (previous is None or previous["trip_pk"] == connection["trip_pk"])
            else transfer_count[dep_stop_id] + 1
        )
        if next_transfers > 2:
            continue
        if arr_stop_id in to_stop_ids:
            trail = build_trail(connection, previous_connection, dep_stop_id)
            if len(connections_to_legs(trail)) == 3:
                results.append(connections_to_legs(trail))
        if arr_stop_id not in earliest_arrival or connection["arr_seconds"] < earliest_arrival[arr_stop_id]:
            earliest_arrival[arr_stop_id] = connection["arr_seconds"]
            previous_connection[arr_stop_id] = connection
            transfer_count[arr_stop_id] = next_transfers
    return results


def collect_scheduled_candidates(
    ctx: PlanContext,
    from_stop_ids: list[int],
    to_stop_ids: list[int],
    max_transfers: int,
    window_start_seconds: int,
    window_end_seconds: int,
    active_services: list[str],
    access_seconds_by_stop: dict[int, int] | None,
    default_access_seconds: int,
) -> dict[str, list[dict[str, Any]]]:
    candidates: dict[str, list[dict[str, Any]]] = {}
    earliest_departure = gt.seconds_to_gtfs_time(window_start_seconds)
    latest_departure = gt.seconds_to_gtfs_time(window_end_seconds)
    for legs in collect_direct_legs(
        ctx,
        from_stop_ids,
        to_stop_ids,
        earliest_departure,
        latest_departure,
        active_services,
        access_seconds_by_stop,
        default_access_seconds,
    ):
        remember_candidate(candidates, legs)
    if max_transfers >= 1:
        for legs in collect_one_transfer_legs(
            ctx,
            from_stop_ids,
            to_stop_ids,
            earliest_departure,
            latest_departure,
            active_services,
            access_seconds_by_stop,
            default_access_seconds,
        ):
            remember_candidate(candidates, legs)
    if max_transfers >= 2:
        for legs in collect_two_transfer_legs(
            ctx, from_stop_ids, to_stop_ids, window_start_seconds, window_end_seconds, active_services
        ):
            if is_accessible_departure(legs[0], from_stop_ids, access_seconds_by_stop, default_access_seconds):
                remember_candidate(candidates, legs)
    return candidates


def find_transit_options(
    conn: psycopg2.extensions.connection,
    from_stop_ids: int | Sequence[int],
    to_stop_ids: int | Sequence[int],
    max_transfers: int,
    depart_at: datetime,
    limit: int,
    access_seconds_by_stop: dict[int, int] | None,
    egress_distance_by_stop: dict[int, int] | None,
) -> list[dict[str, Any]]:
    from_ids = normalize_stop_ids(from_stop_ids)
    to_ids = normalize_stop_ids(to_stop_ids)
    if not from_ids or not to_ids:
        return []
    if len(from_ids) == 1 and len(to_ids) == 1 and from_ids[0] == to_ids[0]:
        return []
    cur = conn.cursor(cursor_factory=RealDictCursor)
    ctx = PlanContext(cur=cur)
    active_services = gt.active_service_ids(cur, depart_at)
    if not active_services:
        cur.close()
        return []
    earliest_seconds = gt.wall_clock_to_service_seconds(depart_at)
    window_start_seconds = earliest_seconds
    if access_seconds_by_stop:
        window_start_seconds = min(access_seconds_by_stop.values())
    window_end_seconds = earliest_seconds + SEARCH_WINDOW_SECONDS
    cand = collect_scheduled_candidates(
        ctx,
        from_ids,
        to_ids,
        max_transfers,
        window_start_seconds,
        window_end_seconds,
        active_services,
        access_seconds_by_stop,
        earliest_seconds,
    )
    if not cand:
        cand = collect_scheduled_candidates(
            ctx,
            from_ids,
            to_ids,
            max_transfers,
            window_start_seconds,
            earliest_seconds + SEARCH_WINDOW_FALLBACK_SECONDS,
            active_services,
            access_seconds_by_stop,
            earliest_seconds,
        )
    cur.close()
    if not cand:
        return []
    paths = list(cand.values())
    paths = remove_dominated_transfer_paths(paths, egress_distance_by_stop)
    paths = collapse_near_departures_for_pattern(paths, egress_distance_by_stop)
    candidate_paths = select_top_candidates(paths, limit, egress_distance_by_stop)
    return [format_transit_path(legs) for legs in candidate_paths]
