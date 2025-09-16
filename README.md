# SulCompany Router

Um roteador simples, flexível e sem dependências, com suporte a:

- Rotas REST (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)
- Parâmetros nomeados (`/usuario/{id}`)
- Parâmetros múltiplos dinâmicos (`/produto/{params*}`)
- middlewares 
- grupos de rotas 
- Cache de rotas para melhorar performance
- rota "clean URLs"

Inspirado no [CoffeeCode Router](https://github.com/robsonvleite/router).

---

## 🚀 Instalação

```bash
composer require sulcompany/router
