# stack/laravel-13.md

Preencher na API. Não contradizer AGENTS.md.

## Travado no produto

- Linguagem: PHP 8.3+
- Framework: Laravel 13, API only
- Banco: MySQL
- Auth de sessão/token: Sanctum (já no repositório)
- Sem Blade de produto

## Ferramentas (completar com o comando real do repo)

- Formatador:
- Análise estática:
- Testes:
- Comando de subir a API:

## Pastas

- `app/Http/Controllers` — fino; sem SQL solto e sem HTML
- `app/Http/Requests` — validação de borda
- `app/Policies` — autorização por objeto
- `app/Services` — caso de uso
- `app/Models` — persistência, sem regra de apresentação
- `database/migrations` — esquema; destrutiva só com autorização

## Armadilhas

- Não adicionar Socialite/Passport/outro pacote de auth sem DEP-04.
- `ability:` do Sanctum não substitui Policy no registro (SEG-06).
- Client secret OIDC só em variável de ambiente do servidor.
