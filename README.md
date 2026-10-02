# Pakistan Law Assistant (RAG)

A small, **source-grounded** question-answering assistant for Pakistani law. It is built with
Retrieval-Augmented Generation (RAG): answers come only from retrieved legal text and include
citations to the act and section they rely on.

> ⚠️ **Disclaimer:** This is a learning and portfolio project. It is **not legal advice** and
> must not be relied on for legal decisions. Always consult a qualified lawyer.

## Status

🚧 Early development. The repository currently contains project setup only.

## What this project demonstrates

- An end-to-end RAG pipeline: ingest → chunk → embed → retrieve → generate → cite
- Vector search in PostgreSQL with `pgvector`
- Running local LLMs and embedding models with Ollama (no paid APIs)
- A Laravel REST API and a React frontend
- Containerised local development with Docker Compose

## Architecture

```
┌──────────┐   question   ┌──────────────┐  embed query   ┌──────────┐
│  React   │ ───────────▶ │ Laravel API  │ ─────────────▶ │  Ollama  │
│   UI     │              │              │ ◀───────────── │ (local)  │
│          │              │              │   vector       │          │
│          │              │              │                │          │
│          │              │   similarity search            │          │
│          │              │ ─────────────▶ ┌────────────┐ │          │
│          │              │ ◀───────────── │ PostgreSQL │ │          │
│          │              │  top-k chunks  │ + pgvector │ │          │
│          │              │                └────────────┘ │          │
│          │              │  prompt + retrieved chunks     │          │
│          │  answer +    │ ─────────────────────────────▶ │          │
│          │ ◀─────────── │ ◀───────────────────────────── │          │
└──────────┘  citations   └──────────────┘  grounded answer└──────────┘
```

1. **Ingest:** load legal texts and record their metadata (act, section/article, source URL).
2. **Chunk:** split the text by legal structure (section or article) where possible.
3. **Embed:** generate embeddings with Ollama and store them in `pgvector`.
4. **Retrieve:** embed the user's question and fetch the most similar chunks.
5. **Generate:** the local LLM answers using only the retrieved chunks.
6. **Respond:** return the answer, the citations and the source snippets.

If no relevant passages are found, the assistant says it cannot answer from its sources.

## Tech stack

| Layer            | Technology                    |
|------------------|-------------------------------|
| Frontend         | React (Vite)                  |
| Backend API      | Laravel (PHP)                 |
| Database         | PostgreSQL + pgvector         |
| LLM / embeddings | Ollama (local models)         |
| Infrastructure   | Docker, Docker Compose        |

## Getting started

> These steps describe the intended setup and will be finalised as the services are built.

**Prerequisites:** Git, Docker and Docker Compose, Ollama

```bash
git clone https://github.com/rajarizwan2007/pakistan-law-assistant-rag-ai.git
cd pakistan-law-assistant-rag-ai
cp .env.example .env
docker compose up -d
# pull the chat and embedding models (exact models TBD)
# run database migrations
# ingest the legal documents
```

## Data sources (planned)

A small, curated set of Pakistani legal texts, for example:

- Constitution of the Islamic Republic of Pakistan, 1973
- Pakistan Penal Code, 1860
- Code of Criminal Procedure, 1898

Raw documents are not committed to this repository. Download links and ingestion
instructions will be added here.

## Roadmap

- [ ] Docker Compose setup: PostgreSQL + pgvector, Ollama, Laravel, React
- [ ] Database schema for documents, chunks and embeddings
- [ ] Ingestion and chunking pipeline
- [ ] Retrieval endpoint (vector similarity search)
- [ ] Answer generation with citations and refusal when sources are missing
- [ ] React chat UI with citation display
- [ ] Evaluation set of sample questions and expected sources
- [ ] Tests and CI with GitHub Actions

## License

This project is licensed under the [MIT License](LICENSE).

The license covers this project's source code only. Legal texts used as source data
remain subject to their own terms.
