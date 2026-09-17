# 2026-09-17 — Conclusão do pedido e pagamento

Status: **teste agora; obrigatório antes do go-live**.

## Agora (testes de lógica)

Professional/admin pode marcar `accepted` e `done` sem esperar a data/turno do slot.
Isso é só para validar o painel. Não é o produto pronto.

## Antes de declarar pronto

1. `done` pelo prestador só é aceito se `now >= slot_date` no fuso America/Sao_Paulo (e, se definido, início do `slot_window`). Código `too_early_to_complete`.
2. Conclusão **real** do atendimento não é o `done` sozinho. O cliente titular do pedido precisa **aceitar como prestado** (`client_confirmed` ou equivalente).
3. Pagamento só libera depois desse aceite do cliente. Sem aceite, não há liberação financeira.
4. Avaliação/depoimento só depois do pedido efetivamente concluído nessa lógica (prestador concluiu no prazo + cliente confirmou prestado).
