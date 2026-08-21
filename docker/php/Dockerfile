FROM node:24-alpine3.24@sha256:d32cdf619f63fe0471182d08996dd516c6275bb5fd31ae06e55a570bd9e1ad43 AS assets-builder

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js ./

RUN npm run build
