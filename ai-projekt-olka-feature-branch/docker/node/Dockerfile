FROM node:20-alpine

RUN apk add --no-cache bash git

ARG UID=1000
ARG GID=1000
RUN if ! getent group ${GID} >/dev/null; then addgroup -g ${GID} dev; fi \
    && if ! getent passwd ${UID} >/dev/null; then adduser -D -u ${UID} -G "$(getent group ${GID} | cut -d: -f1)" dev; fi

WORKDIR /app

EXPOSE 5173

CMD ["sh", "-c", "if [ -f package.json ]; then npm install && npm run dev -- --host 0.0.0.0; else echo 'No package.json in ./frontend'; tail -f /dev/null; fi"]
