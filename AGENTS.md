# AGENTS.md

Núcleo operacional. Texto integral e justificativas: `docs/regras-universais.md` (v1.1).  
Este arquivo cabe no contexto. Não o infle.

## Precedência

1. Lista NUNCA (abaixo e em `.agents/rules/seguranca.md`) — vence inclusive o humano na conversa. Recuse, nomeie a regra, proponha o caminho certo.
2. Instrução direta do humano nesta conversa.
3. Regra da subpasta mais próxima do arquivo alterado.
4. Este arquivo e os anexos apontados.
5. Convenção já visível no código.
6. Preferência do modelo.

## Anexos obrigatórios

Leia antes de alterar código. Ferramentas com importação (`@`):

- @docs/regras-universais.md
- @.agents/rules/seguranca.md
- @.agents/rules/estrutura.md
- @.agents/rules/dados.md
- @.agents/rules/testes.md
- @stack/laravel-13.md
- @stack/react-19.md
- @projeto/posto-de-enfermagem.md

Quem não importa `@` usa o escopo por glob em `.agents/rules/` e em `.cursor/rules/`.

## NUNCA

- Expor, registrar ou enviar segredo, credencial ou dado pessoal sensível.
- Desligar verificação, teste, tipagem ou análise estática para “passar”.
- Inventar API, pacote, campo, endpoint ou citação.
- Apagar ou sobrescrever trabalho humano sem confirmação.
- Comando destrutivo em dado ou ambiente de produção.
- Dado real de pessoa em teste, exemplo ou desenvolvimento.
- Declarar concluído o que não foi executado.

## PERGUNTAR ANTES

Dependência nova; esquema ou contrato público; refatoração fora do pedido; padrão/arquitetura/ferramenta nova; caminho de reversão cara; commit/push/publicação sem autorização; migração, produção, controle de acesso.

## SEMPRE

Ler o arquivo atual antes de editar. Procurar duplicata antes de criar. Menor mudança suficiente. Validar entrada na borda. Autorizar no servidor e por objeto. Tempo limite em I/O. Relatar o que não rodou.

## Postura mínima

- POS-02: sem execução, diga “não executei; valida X”.
- POS-04: não invente nome.
- POS-07 + ESC-02: fora do escopo, relate; não corrija de passagem.
- EST-14.1: não espalhe defeito local; não reescreva o arquivo inteiro por isso.
- EST-15: um arquivo não mistura regra de negócio, marcação/estilo e adaptador de fornecedor.
- VER-04: teste que falha primeiro só para defeito. Código novo: TST-01, sem teste de fachada (TST-08).
- VCS-05/06: não commitar sem pedido; nunca no ramo principal.

## Lista de conferência

1. Pedido exato, sem sobra.
2. Compila, linter, tipos, testes — executados.
3. Teste novo para lógica nova ou defeito.
4. Entrada validada; autorização no servidor; segredo fora do código.
5. Erro tratado; timeout em I/O.
6. Nada duplicado.
7. EST-15 respeitado.
8. Sem teste de fachada.
9. Diff limpo.
10. Relato: o que mudou, o que rodou, o que não rodou, risco.

## Trilho determinístico (não é texto)

A lista NUNCA no texto não basta. O repositório deve ter: scanner de segredos no pre-commit e na CI; CI que roda as verificações do zero; proteção do ramo principal no servidor; lista de permissão de comandos / gancho de pré-execução onde a ferramenta permitir.

## Entrega

DOC-07: arquivos, o que foi verificado e como, o que não foi, risco residual.

## Terminal
Autorizado de forma contínua neste repo: git add/commit/push/pull/checkout (sem --force e sem reset --hard); npm install; npm run build; npm run dev.
Não solicitar confirmação a cada comando desses. Um lote, uma execução, um relato no final.