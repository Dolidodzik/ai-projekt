import os
from datetime import datetime

import psycopg2
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app.transit_engine import find_transit_options

app = FastAPI(title="Route planner", version="1.0.0")


class PlanTransitRequest(BaseModel):
    from_stop_ids: list[int]
    to_stop_ids: list[int]
    max_transfers: int = Field(default=3, ge=0, le=3)
    depart_at: datetime
    limit: int = Field(default=5, ge=1, le=20)
    access_seconds_by_stop: dict[str, int] | None = None
    egress_distance_by_stop: dict[str, int] | None = None


def _int_key_dict(d: dict[str, int] | None) -> dict[int, int] | None:
    if d is None:
        return None
    return {int(k): v for k, v in d.items()}


def _db_conn():
    host = os.environ.get("DB_HOST", "db")
    port = int(os.environ.get("DB_PORT", "5432"))
    name = os.environ.get("DB_DATABASE", "ai2_projekt")
    user = os.environ.get("DB_USERNAME", "ai2_user")
    password = os.environ.get("DB_PASSWORD", "ai2_secret")
    return psycopg2.connect(host=host, port=port, dbname=name, user=user, password=password)


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/plan-transit")
def plan_transit(body: PlanTransitRequest):
    try:
        conn = _db_conn()
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=503, detail=f"Database unavailable: {exc}") from exc
    try:
        options = find_transit_options(
            conn,
            body.from_stop_ids,
            body.to_stop_ids,
            body.max_transfers,
            body.depart_at,
            body.limit,
            _int_key_dict(body.access_seconds_by_stop),
            _int_key_dict(body.egress_distance_by_stop),
        )
    finally:
        conn.close()
    return {"options": options}
