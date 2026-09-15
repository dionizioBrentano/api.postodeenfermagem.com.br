# projeto/posto-de-enfermagem.md

Domínio. Completar e versionar na API.

## Produtos e hosts

- API: `api.postodeenfermagem.com.br` — contrato, dados, segurança.
- Enfaci: `enfaci.com.br` — tenant 0, frente operacional.
- Equipe Enfermagem: `equipeenfermagem.com.br` — vitrine da rede e captação.
- Florence: interface interna futura sobre a mesma API. Sem banco próprio.

## Identidade

- Pessoa canônica: `users`.
- Provas de login: `user_identities` (`local`, `google`, `microsoft`; Apple/passkey depois).
- Não fundir contas só por e-mail.
- CPF, COREN e RT não vêm do Google/Microsoft.

## Responsabilidade técnica

- RT do sistema ≠ RT da oferta ≠ RT da Enfaci.
- A API não prescreve procedimento (indicação, técnica, “como fazer”).
- Tipo de procedimento da plataforma = rótulo. Oferta = montagem do titular, com RT identificado para publicar.

## Tenant

- Todo tenant tem hierarquia vertical (árvore de unidades) e horizontal (trilhos: assistencial, administrativo, comercial, operacional).
- Membership = pessoa + tenant + unidade + trilho + papel.
- Plataforma não herda RT de parceiro.

## Escopo atual de código

- Retificar acesso (identidades + OIDC) sem apagar PEP já existente.
- Não publicar protocolo clínico institucional.

## Exceções aceitas ao núcleo

Nenhuma até decisão datada em `decisoes/`.
