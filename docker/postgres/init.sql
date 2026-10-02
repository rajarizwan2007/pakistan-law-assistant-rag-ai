-- Runs once, when the Postgres data volume is first created.
-- Separate database for PHPUnit so tests never wipe development data.
CREATE DATABASE law_assistant_test OWNER law;

\connect law_assistant
CREATE EXTENSION IF NOT EXISTS vector;

\connect law_assistant_test
CREATE EXTENSION IF NOT EXISTS vector;
