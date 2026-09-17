# projeto/posto-de-enfermagem.md

Domínio. Completar e versionar na API.

## Produtos e hosts

- API: `api.postodeenfermagem.com.br` — contrato, dados, segurança. Hosting cPanel, PHP 8.3. Pasta no servidor: `$HOME/api.postodeenfermagem.com.br` (usuário `postodee`).
- Enfaci: `enfaci.com.br` — tenant 0, frente operacional. Pasta no servidor: `$HOME/enfaci.com.br`.
- Equipe Enfermagem: `equipeenfermagem.com.br` — vitrine da rede e captação.
- Florence: interface interna futura sobre a mesma API. Sem banco próprio.

## Publicação (permanente)

- O servidor de produção **não tem Node.js**. Nunca rodar `npm`, `npx` ou `vite` no cPanel.
- Front: build **só no computador local** (`npm run build`). Subir o conteúdo de `dist/` pelo `.sh` ou File Manager. Variáveis `VITE_*` entram no JS na hora do build no PC, não no runtime do hosting.
- API: no servidor, `git pull` da branch + `php artisan migrate --force`. Sem Node.

## Identidade

- Pessoa canônica: `users`.
- Provas de login: `user_identities` (`local`, `google`, `microsoft`; Apple/passkey depois).
- Não fundir contas só por e-mail.
- CPF, COREN e RT não vêm do Google/Microsoft.
- Login local: e-mail ou CPF ou telefone + senha.
- Step-up MFA (TOTP) obrigatório para prontuário, evolução, agenda e alteração de dados cadastrais.

## Responsabilidade técnica

- RT do sistema ≠ RT da oferta ≠ RT da Enfaci.
- A API não prescreve procedimento (indicação, técnica, “como fazer”).
- Tipo de procedimento da plataforma = rótulo. Oferta = montagem do titular, com RT identificado para publicar.

## Tenant

- Todo tenant tem hierarquia vertical (árvore de unidades) e horizontal (trilhos: assistencial, administrativo, comercial, operacional).
- Membership = pessoa + tenant + unidade + trilho + papel.
- Plataforma não herda RT de parceiro.

## Escopo atual de código

- Retificar acesso (identidades + OIDC + identificadores + step-up) sem apagar PEP já existente.
- Não publicar protocolo clínico institucional.

## Exceções aceitas ao núcleo

Nenhuma até decisão datada em `decisoes/`.
