---
name: dados
trigger: glob
globs:
  - "database/**"
  - "**/migrations/**"
  - "**/Models/**"
  - "app/Models/**"
  - "config/**"
  - "composer.json"
  - "composer.lock"
  - "package.json"
  - "package-lock.json"
---

# Dados, persistência e dependências

## Esquema

DAD-01 — Mudança de esquema = migração versionada, reversível, sem passo manual.
DAD-02 — Migração destrutiva só com autorização explícita.
DAD-03 — Vários registros relacionados: transação.
DAD-04 — Coleção: paginar; teto definido; não carregar tudo.
DAD-05 — Sem query dentro de loop. Busca em lote.
DAD-06 — Índice acompanha filtro/ordem frequente.
DAD-07 — Mínimo de dado pessoal; retenção curta; cifrar sensível em repouso; auditar acesso quando a lei exigir.
DAD-08 — Soft delete é decisão documentada, com expurgo. Senão, exclusão é exclusão.
DAD-09 — Dev/teste não apontam para produção.
DAD-10 — Cache: chave, TTL e invalidação. Os três.

ESC-04 — Não editar lockfile nem migração já aplicada sem pedido. Lockfile só muda quando o humano autorizar a dependência (DEP-04).

## Dependências

DEP-01 — Biblioteca padrão ou já no projeto primeiro.
DEP-02 — Pacote existe no registro oficial, nome exato, manutenção ativa. Não inventar nome.
DEP-03 — Versão fixada e lock versionado.
DEP-04 — Humano autoriza dependência nova: para quê, custo, alternativa, licença.
DEP-05 — Vulnerabilidade alta tratada antes de entregar.
DEP-06 — Fornecedor atrás de interface própria. Sem SDK espalhado.
DEP-07 — Terceiro fora: degradar, cache, fila ou recusar. Não derrubar o sistema inteiro.
