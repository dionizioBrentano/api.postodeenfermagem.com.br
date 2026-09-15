# Instalar o pacote de regras

1. Este pacote já está na raiz da API nesta branch.
2. Cole o texto integral da versão 1.1 em `docs/regras-universais.md` se o arquivo ainda for o stub.
3. Ajuste globs em `.agents/rules/*.md` se as pastas do repo divergirem.
4. Nos fronts, copie `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.agents/rules/seguranca.md`, `.agents/rules/estrutura.md`, `.agents/rules/testes.md` e `stack/react-19.md`. Não copie `stack/laravel-13.md`.
5. Não crie `~/.gemini/GEMINI.md` de projeto. O `GEMINI.md` da raiz já aponta para `AGENTS.md`.
6. Trilho determinístico (humano / infra), fora deste pacote:
   - proteção de `master`/`main` no GitHub
   - secret scanning
   - CI rodando testes e linter do zero
