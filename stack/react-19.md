# stack/react-19.md

Preencher em cada front (Enfaci, Equipe Enfermagem, app).

## Travado no produto

- React 19
- TypeScript estrito quando o repo já for TS
- Sem regra de negócio de tenant/RT/identidade no componente visual (EST-15)

## Ferramentas (completar)

- Empacotador:
- Formatador:
- Testes:
- Comando de dev:

## Pastas típicas

- `pages` / rotas — composição
- `components` — apresentação
- `services` — cliente HTTP da API
- `types` — contrato consumido, não inventado

## Armadilhas

- `VITE_*` / equivalente é público. Client ID OAuth pode; secret não (SEG-01).
- Esconder botão ≠ autorização (SEG-05).
- Não fundir contas no front. Identidade resolve na API.
