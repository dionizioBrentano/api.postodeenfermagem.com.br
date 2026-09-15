---
name: estrutura
trigger: glob
globs:
  - "app/**"
  - "src/**"
  - "resources/**"
  - "routes/**"
  - "database/**"
---

# Estrutura, escopo e erros

Aplica-se a código de aplicação. Texto integral: docs/regras-universais.md (EST, ESC, ERR, PRE, POS).

## Escopo

ESC-01 — Menor alteração que resolve o pedido.
ESC-02 — Sem refatoração, rename em massa, upgrade ou formatação de passagem.
ESC-03 — Um propósito por conjunto de mudanças.
ESC-05 — Não reescrever arquivo inteiro se o patch pontual basta.
ESC-07 — Mudança de contrato público exige aviso.
ESC-08 — Sem código comentado “por precaução”.
ESC-09 — Sem arquivo extra não pedido.

## Leitura e camadas

PRE-01 — Ler o arquivo atual (ou o trecho relevante) antes de editar.
PRE-03 — Procurar equivalente no repo antes de criar.
EST-03 — Uma função, uma coisa, um nível.
EST-04 — Retorno antecipado; sem ninho fundo.
EST-05 — Calcular separado de gravar/enviar.
EST-07 — Negócio não conhece SQL, HTTP do framework nem SDK do fornecedor. Adaptador na borda.
EST-10 — Generalizar na terceira repetição real.
EST-14.1 — Imitar o local no patch. Se o local viola regra, a parte nova nasce conforme; relate o desvio; não “limpe” o resto.
EST-15 — Um arquivo não mistura regra de negócio, marcação/estilo e adaptador de fornecedor. Diff: o arquivo que ganhou consulta + markup + decisão no mesmo PR está errado.

## Erros

ERR-01 — Sem catch vazio.
ERR-02 — Falhar cedo na entrada.
ERR-03 — Mensagem: o quê, qual valor, o que fazer.
ERR-04 — Detalhe interno no log, não no usuário. Correlação no lugar do rastro.
ERR-05 — Esperado ≠ inesperado.
ERR-06 — Todo I/O com falha explícita e timeout.
ERR-07 — Retry só transitório, com limite e backoff; idempotência se repetir.
ERR-10 — Função crítica: log de sucesso, log de falha, um jeito de ver se está viva.
