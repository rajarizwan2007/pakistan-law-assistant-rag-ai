FROM node:24-alpine

# Pre-create node_modules owned by the "node" user (UID 1000). A new named volume
# mounted here copies this ownership, so `npm install` can write to it without root.
RUN mkdir -p /app/node_modules && chown node:node /app/node_modules

WORKDIR /app
USER node
