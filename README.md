# Posto de Enfermagem API

API Whitelabel multi-tenant projetada para registro seguro de dados de saúde.

## Stack Tecnológica
- **Framework:** Laravel 13
- **Linguagem:** PHP 8.3
- **Banco de Dados:** SQLite (MVP)

## Repositório
[Inserir Link do Repositório Aqui]

## Como Configurar o Ambiente Local

1. Clone este repositório.
2. Observe que a pasta `vendor` **NÃO** é versionada (por questões de segurança e boas práticas).
3. Instale as dependências do PHP rodando:
   ```bash
   composer install
   ```
4. Copie o arquivo de exemplo de ambiente:
   ```bash
   cp .env.example .env
   ```
5. Crie o banco de dados e rode as migrations:
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```
6. Inicie o servidor local:
   ```bash
   php artisan serve
   ```

A API estará disponível em `http://localhost:8000/api/v1`.
