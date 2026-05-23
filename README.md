## Uruchamianie

```bash
cp .env.example .env
docker compose up --build
```

W pliku `.env` ustaw `APP_KEY`

Po pierwszym uruchomieniu kontenerów:

```bash
docker compose exec app php artisan key:generate --show
```

potem zrestartuj aplikację:

```bash
docker compose restart 
```


