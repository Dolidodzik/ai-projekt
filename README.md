
Automatyczny start (dev)
```bash
docker compose up -d --build
```

- `app` uruchamia automatycznie:
  - `migrate --seed --force` gdy baza jest pusta,
  - `migrate --force` gdy baza ma już migracje.
- `scheduler` uruchamia się automatycznie i wykonuje zadania z harmonogramu.

Ręczny import GTFS (opcjonalnie):

```bash
docker compose exec app php artisan gtfs:sync
docker compose exec app php artisan gtfs:sync --force
```

Harmonogram GTFS o 00:00

```bash
docker compose logs -f scheduler
```
