from __future__ import annotations

from datetime import datetime, timezone
from zoneinfo import ZoneInfo

from psycopg2 import sql

WARSAW = ZoneInfo("Europe/Warsaw")


def _service_id_cell(row: object) -> str:
    if isinstance(row, dict):
        v = row.get("service_id")
    else:
        v = row[0]  # type: ignore[index]
    return str(v) if v is not None else ""


def to_local(dt: datetime) -> datetime:
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(WARSAW)


def wall_clock_to_service_seconds(dt: datetime) -> int:
    lt = to_local(dt)
    return lt.hour * 3600 + lt.minute * 60 + lt.second


def gtfs_time_to_seconds(t: str) -> int:
    parts = str(t).split(":")
    h = int(parts[0]) if parts else 0
    m = int(parts[1]) if len(parts) > 1 else 0
    s = int(parts[2]) if len(parts) > 2 else 0
    return h * 3600 + m * 60 + s


def seconds_to_gtfs_time(seconds: int) -> str:
    h = seconds // 3600
    m = (seconds % 3600) // 60
    s = seconds % 60
    return f"{h:02d}:{m:02d}:{s:02d}"


def active_service_ids(cur, dt: datetime) -> list[str]:
    local = to_local(dt)
    date_string = local.strftime("%Y-%m-%d")
    day_columns = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"]
    day_column = day_columns[int(local.strftime("%w"))]

    q = sql.SQL(
        "SELECT service_id FROM gtfs_calendars WHERE start_date <= %s AND end_date >= %s AND {} = %s"
    ).format(sql.Identifier(day_column))
    cur.execute(q, (date_string, date_string, "1"))
    calendar_services = [x for x in (_service_id_cell(r) for r in cur.fetchall()) if x]

    cur.execute(
        """
        SELECT service_id FROM gtfs_calendar_dates
        WHERE date = %s AND exception_type = 1 AND service_id IS NOT NULL
        """,
        (date_string,),
    )
    added = [x for x in (_service_id_cell(r) for r in cur.fetchall()) if x]

    cur.execute(
        """
        SELECT service_id FROM gtfs_calendar_dates
        WHERE date = %s AND exception_type = 2 AND service_id IS NOT NULL
        """,
        (date_string,),
    )
    removed = {x for x in (_service_id_cell(r) for r in cur.fetchall()) if x}

    merged = [x for x in calendar_services if x not in removed] + added
    out: list[str] = []
    seen: set[str] = set()
    for x in merged:
        if x not in seen:
            seen.add(x)
            out.append(x)
    return out
