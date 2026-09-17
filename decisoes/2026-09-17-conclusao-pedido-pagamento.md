# 2026-09-17 — Conclusão, cancelamento e pagamento

Status: **teste agora; obrigatório antes do go-live**.

## Agora (testes de lógica)

Professional/admin pode marcar `accepted` e `done` sem esperar a data/turno do slot.
Isso é só para validar o painel. Não é o produto pronto.

## Antes de declarar pronto

1. `done` pelo prestador só é aceito se `now >= slot_date` no fuso America/Sao_Paulo (e, se definido, início do `slot_window`). Código `too_early_to_complete`.
2. Conclusão **real** do atendimento não é o `done` sozinho. O cliente titular precisa **aceitar como prestado**. Pagamento só depois desse aceite.
3. Avaliação/depoimento só depois dessa conclusão real.

## Cancelamento pelo prestador ANTES do slot

O prestador original pode cancelar a execução **antes** da data/turno. Isso não some o pedido do cliente.

Dispara para a **rede** (hoje: outros service_points do mesmo tenant; depois: tenants na Equipe) um novo pedido de trabalho **naquele mesmo horário**.

Dois caminhos, ambos exigem **aceite do cliente no sistema**:

- A) Substituição: sistema sugere outro profissional/ponto disponível no slot. Cliente aceita → troca `service_point`/`offering` e o pedido segue `accepted` com o novo prestador.
- B) Reagenda com o mesmo prestador: ele informa nova data/turno. Cliente aceita → atualiza o slot. Cliente recusa → volta à trilha A ou cancela de fato.

Sem aceite do cliente não há substituição nem nova data válida.
