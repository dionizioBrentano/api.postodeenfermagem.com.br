---
name: testes
trigger: glob
globs:
  - "tests/**"
  - "test/**"
  - "**/*Test.php"
  - "**/*.test.ts"
  - "**/*.test.tsx"
  - "**/*.spec.ts"
  - "**/*.spec.tsx"
---

# Testes e verificação

## Verificar de fato

VER-01 — Entrega: compila, linter limpo, tipos limpos, testes existentes passando.
VER-02 — Rodar o teste. Não deduzir.
VER-03 — Sem aviso novo.
VER-04 — Defeito: primeiro o teste que falha por causa dele; depois o conserto. Não estender a todo código novo.
VER-05 — Bordas: vazio, nulo, zero, negativo, grande, duplicado, ordem, acento, concorrência se couber.
VER-06 — Sem ambiente: listar os comandos exatos para o humano.
VER-07 — Relere o diff: debug, valor temporário, import morto, TODO solto.

## Qualidade do teste

TST-01 — Lógica nova de decisão nasce com teste. Se não deu, diga por quê.
TST-02 — Comportamento observável, não miolo privado.
TST-03 — Independente, determinístico, qualquer ordem. Sem relógio real, rede real ou estado de outro teste.
TST-04 — Feliz, erro e borda.
TST-05 — Nome = condição + resultado esperado.
TST-06 — Muitos unitários; alguns de integração nas fronteiras; poucos e2e nos fluxos críticos.
TST-07 — Dublê só na borda externa. Não simular a regra que se quer provar.
TST-08 — Proibido teste de fachada: sem assert real, só “não lançou”, cópia da implementação, lote para cobertura. Se não pode falhar quando o comportamento quebra, apague.
TST-09 — Flaky é defeito: corrige ou some. Não reexecutar até passar.
TST-10 — Dado de teste fictício. Nunca pessoa, cliente ou paciente reais.
