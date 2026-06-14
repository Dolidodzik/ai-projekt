## Uruchamianie

Pełna dokumentacja projektu: **[DOKUMENTACJA.md](./DOKUMENTACJA.md)**

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

## Baza danych (backup)



### Konta demo (z backupu)

| Rola | Email | Hasło |
|------|-------|-------|
| Admin | `admin@example.com` | `password123` |
| Użytkownik testowy | `test@example.com` | `password` |
| Pozostali użytkownicy | `*.@example.com` | `password123` |

### Reset bazy do stanu z backupu

```bash
docker compose down -v
docker compose up --build
```

### Regeneracja backupu

Po ręcznych zmianach w bazie (np. przez panel admina):

```bash
./scripts/generate-db-backup.sh
```
