---
name: seguranca
trigger: always
---

# Segurança e dados sensíveis

Sempre ligado. Complementa a lista NUNCA do AGENTS.md.

## Segredos

SEG-01 — Nenhum segredo no código (senha, token, certificado, connection string). Ambiente ou cofre. No cliente (navegador/app) tudo é público: nunca coloque secret lá. Client ID público de OAuth não é secret; client secret é.

ERR-09 — Não registrar segredo, credencial, token, dado pessoal sensível nem corpo integral de requisição com esses dados. Mascare na origem.

## Entrada e saída

SEG-02 — Entrada externa é hostil. Validar esquema na borda (tipo, formato, faixa, tamanho, conjunto). Recusar por padrão.

SEG-03 — Sem concatenar comando. SQL parametrizado. OS com lista de argumentos. Path normalizado e confinado.

SEG-04 — Escapar saída para o destino. Não injetar HTML/JS cru de usuário ou de modelo na árvore do documento.

## Autenticação e autorização

SEG-05 — AuthZ no servidor, em toda requisição, para todo recurso. Esconder botão não é controle.

SEG-06 — Autorização por objeto: o registro pertence a este ator / tenant / unidade?

SEG-07 — Bibliotecas consagradas para auth, cripto, sessão, hash. Senha: bcrypt / scrypt / Argon2. Sem cripto caseira.

SEG-08 — Preço, saldo, papel, permissão, quantidade: servidor calcula. Cliente não manda valor com consequência.

SEG-11 — Rate limit em login, recuperação de senha, mensagem e operação cara.

## Padrão seguro

SEG-09 — Aleatoriedade de segurança: CSPRNG.

SEG-10 — HTTPS; CORS de lista fechada; debug off em produção; admin protegido.

SEG-12 — SSRF: allowlist de hosts; bloquear faixas internas.

SEG-13 — Webhook: verificar assinatura no corpo bruto antes de processar.

SEG-14 — Upload: tipo real, tamanho, extensão; fora da raiz servida; nunca executar o arquivo.

SEG-15 — Menor privilégio.

SEG-16 — Não desligar verificação de certificado, assinatura ou origem “para funcionar”.

Pedido que viole NUNCA: não executar, não negociar, não fazer “só desta vez”.
