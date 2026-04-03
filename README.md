# Temperinho — API Backend

> API RESTful construída com **Laravel 11** para a plataforma de culinária inclusiva Temperinho. Responsável por toda a lógica de negócio, autenticação, gerenciamento de conteúdo e comunicação com os serviços externos.

---

## Sumário

- [Visão Geral](#visão-geral)
- [Stack Tecnológica](#stack-tecnológica)
- [Arquitetura](#arquitetura)
- [Funcionalidades](#funcionalidades)
- [Pré-requisitos](#pré-requisitos)
- [Instalação Local](#instalação-local)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Endpoints da API](#endpoints-da-api)
- [Autenticação](#autenticação)
- [Upload de Imagens](#upload-de-imagens)
- [Busca Full-Text](#busca-full-text)
- [Filas e Notificações](#filas-e-notificações)
- [Cache](#cache)
- [Planos e Limites](#planos-e-limites)
- [Testes](#testes)
- [Deploy com Docker](#deploy-com-docker)
- [Monitoramento](#monitoramento)

---

## Visão Geral

O **Temperinho API** é o coração do ecossistema Temperinho. Expõe uma API REST consumida tanto pelo painel administrativo quanto pelo site público. A aplicação foi projetada para ser **escalável**, **testável** e de fácil manutenção, seguindo os padrões estabelecidos pelo ecossistema Laravel.

---

## Stack Tecnológica

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.2 |
| Framework | Laravel 11 |
| Autenticação | Laravel Sanctum 4 |
| OAuth Social | Laravel Socialite 5 |
| Busca | Meilisearch via Laravel Scout 10 |
| Banco de Dados (Prod) | PostgreSQL 15 |
| Banco de Dados (Dev) | SQLite |
| Cache / Sessão / Filas | Redis (via Predis) |
| Armazenamento de Arquivos | AWS S3 (via Flysystem) |
| E-mail Transacional | AWS SES |
| Monitoramento de Erros | Sentry |
| Servidor Web (Prod) | Nginx |
| Containerização | Docker / Docker Compose |
| Testes | PHPUnit 11 |

---

## Arquitetura

O projeto adota uma separação clara de responsabilidades com os seguintes padrões:

### Service Layer
A lógica de negócio vive em `app/Services`, dividida por domínio (ex: `Post/CreatePost`, `Recipe/UpdateRecipe`). Os controllers são mantidos enxutos — apenas recebem a requisição, delegam ao serviço e retornam a resposta.

### API Resources
Toda a serialização JSON passa por `app/Http/Resources`, garantindo contratos de resposta estáveis e desacoplados do schema do banco.

### Form Requests
Validação e autorização centralizadas em `app/Http/Requests`. Cada operação tem seu próprio `FormRequest`, que é injetado automaticamente pelo container do Laravel.

### Policies
Toda regra de autorização (`can update`, `can delete`, etc.) vive em `app/Policies`, registradas no `AuthServiceProvider`.

### Eloquent & Local Scopes
Queries reutilizáveis são encapsuladas em `scopeFilter` nos modelos, evitando duplicação nos serviços e controllers.

### Relações Polimórficas
`Image`, `Comment` e `Rating` utilizam relações polimórficas (`MorphTo / MorphMany`), permitindo que sejam associados a qualquer modelo (`Post`, `Recipe`, `User`) de forma limpa.

### Traits Compartilhados
- `BaseListService` — lógica genérica de listagem com suporte a Meilisearch e query tradicional.
- `HasUserContentListing` — listagem do conteúdo próprio do usuário autenticado e favoritos.
- `ManagesResourceCaching` — cache por tag para controllers de recursos simples.
- `HasStandardFiltering` — regras de validação de filtros reutilizáveis entre Form Requests.

---

## Funcionalidades

### Autenticação
- Registro, login e logout com token via **Sanctum**
- Recuperação de senha por e-mail (link temporário)
- Login social via **Google** (e arquitetura preparada para GitHub/Facebook) com **Socialite**
- Tokens por sessão (`currentAccessToken()->delete()` no logout)

### Usuários
- CRUD completo com upload de foto de perfil
- Controle de papéis (`RolesEnum`) — interno vs. externo
- Filtro por papel, nome, e-mail, telefone e faixa de data de nascimento

### Receitas
- CRUD com ingredientes, passos e dietas (sincronização relacional completa no update)
- Categorias, dietas e unidades de medida gerenciáveis
- Upload de imagem com rollback automático em caso de falha na transação DB
- Avaliações (1–5 estrelas) e comentários polimórficos
- Sistema de favoritos por usuário

### Posts / Blog
- CRUD com categorias e tópicos
- Conteúdo associado à empresa do usuário (`company_id`)
- Avaliações, comentários e favoritos

### Empresas
- Cadastro de empresa vinculada a um usuário
- Upload de logo
- Assinaturas de planos e pagamentos

### Planos e Assinaturas
- Planos com limites configuráveis (`max_posts`, `max_recipes`, `max_banners`, etc.)
- Middleware `CheckPlanLimit` bloqueia criação quando o limite mensal é atingido
- Notificação por e-mail ao assinar/expirar um plano

### Pagamentos
- CRUD de pagamentos vinculados a assinaturas
- Métodos de pagamento configuráveis
- Notificações de nova fatura e pagamento confirmado

### Interações
- **Comentários** — polimórficos, suportam `posts` e `recipes`
- **Ratings** — `updateOrCreate` por usuário/recurso (um voto por usuário)
- Cache por tag com flush automático ao criar/atualizar/deletar

### Marketing
- Formulário de contato (`CustomerContact`) com notificação por e-mail
- Newsletter — inscrição e cancelamento com e-mail de confirmação

---

## Pré-requisitos

- PHP 8.2+
- Composer 2+
- Node.js (opcional, apenas para assets)
- SQLite (desenvolvimento) ou PostgreSQL 15 (produção)
- Redis (opcional localmente, necessário em produção)
- Docker e Docker Compose (para ambiente containerizado)

---

## Instalação Local

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/temperinho-api.git
cd temperinho-api

# 2. Instale as dependências PHP
composer install

# 3. Configure o ambiente
cp .env.example .env
php artisan key:generate

# 4. Configure o banco (SQLite para desenvolvimento rápido)
touch database/database.sqlite
# Ajuste DB_CONNECTION=sqlite no .env

# 5. Execute migrações e seeders
php artisan migrate --seed

# 6. (Opcional) Inicie filas e scheduler
php artisan queue:work
php artisan schedule:work

# 7. Inicie o servidor
php artisan serve
```

A API estará disponível em `http://localhost:8000/api`.

---

## Variáveis de Ambiente

Copie `.env.example` para `.env` e preencha as variáveis:

### Aplicação
| Variável | Descrição |
|---|---|
| `APP_KEY` | Gerado via `php artisan key:generate` |
| `APP_URL` | URL base da API |
| `APP_FRONTEND_URL` | URL do frontend (para CORS e links de e-mail) |
| `APP_TIMEZONE` | Fuso horário (`UTC` recomendado) |

### Banco de Dados
| Variável | Descrição |
|---|---|
| `DB_CONNECTION` | `pgsql` (produção) ou `sqlite` (dev) |
| `DB_HOST` | Host do PostgreSQL |
| `DB_DATABASE` | Nome do banco |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciais |

### Redis
| Variável | Descrição |
|---|---|
| `REDIS_HOST` | Host do Redis |
| `REDIS_PASSWORD` | Senha (ou `null`) |
| `QUEUE_CONNECTION` | `redis` em produção |
| `CACHE_STORE` | `redis` em produção |
| `SESSION_DRIVER` | `redis` em produção |

### AWS (S3 + SES)
| Variável | Descrição |
|---|---|
| `AWS_ACCESS_KEY_ID` | Chave de acesso AWS |
| `AWS_SECRET_ACCESS_KEY` | Chave secreta AWS |
| `AWS_DEFAULT_REGION` | Ex: `sa-east-1` |
| `AWS_BUCKET` | Nome do bucket S3 |
| `AWS_URL` | CDN/CloudFront URL (opcional) |
| `MAIL_MAILER` | `ses` em produção |

### Meilisearch
| Variável | Descrição |
|---|---|
| `MEILISEARCH_HOST` | `http://meilisearch:7700` |
| `MEILISEARCH_KEY` | Master key do Meilisearch |

### OAuth (Google)
| Variável | Descrição |
|---|---|
| `GOOGLE_CLIENT_ID` | Client ID do Google Console |
| `GOOGLE_CLIENT_SECRET` | Client Secret |
| `GOOGLE_REDIRECT_URI` | URI de callback cadastrada no Google |

### Admin Seed
| Variável | Descrição |
|---|---|
| `ADMIN_NAME` | Nome do usuário admin inicial |
| `ADMIN_EMAIL` | E-mail do admin |
| `ADMIN_PASSWORD` | Senha do admin |
| `ADMIN_ROLE` | Papel (`admin`) |

---

## Endpoints da API

Todas as rotas são prefixadas com `/api`.

### Autenticação
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `POST` | `/auth/login` | ❌ | Login com e-mail e senha |
| `POST` | `/auth/logout` | ✅ | Revoga o token atual |
| `POST` | `/auth/forgot-password` | ❌ | Envia link de recuperação |
| `POST` | `/auth/reset-password` | ❌ | Redefine a senha com token |
| `GET` | `/auth/{provider}/redirect` | ❌ | Redireciona para OAuth (Google) |
| `GET` | `/auth/{provider}/callback` | ❌ | Callback do OAuth |

### Usuários
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `GET` | `/users` | ✅ | Lista usuários (com filtros) |
| `POST` | `/users` | ❌ | Registro de novo usuário |
| `GET` | `/users/me` | ✅ | Usuário autenticado |
| `GET` | `/users/{id}` | ✅ | Detalhe de usuário |
| `PUT/PATCH` | `/users/{id}` | ✅ | Atualiza dados (com imagem) |
| `DELETE` | `/users/{id}` | ✅ | Remove conta |
| `PATCH` | `/users/{id}/role` | ✅ | Atualiza papel do usuário |
| `POST` | `/users/favorites/post` | ✅ | Toggle favorito (post) |
| `POST` | `/users/favorites/recipe` | ✅ | Toggle favorito (receita) |

### Receitas
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `GET` | `/recipes` | ❌ | Lista (busca, filtros, paginação) |
| `POST` | `/recipes` | ✅ | Cria receita (com imagem) |
| `GET` | `/recipes/{id}` | ❌ | Detalhe completo |
| `PUT/PATCH` | `/recipes/{id}` | ✅ | Atualiza receita |
| `DELETE` | `/recipes/{id}` | ✅ | Remove receita |
| `GET` | `/recipes/user` | ✅ | Receitas do usuário autenticado |
| `GET` | `/recipes/favorites` | ✅ | Receitas favoritas |

### Posts
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `GET` | `/posts` | ❌ | Lista (busca, filtros, paginação) |
| `POST` | `/posts` | ✅ | Cria post (com imagem) |
| `GET` | `/posts/{id}` | ❌ | Detalhe completo |
| `PUT/PATCH` | `/posts/{id}` | ✅ | Atualiza post |
| `DELETE` | `/posts/{id}` | ✅ | Remove post |
| `GET` | `/posts/user` | ✅ | Posts do usuário autenticado |
| `GET` | `/posts/favorites` | ✅ | Posts favoritos |

### Comentários
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `GET` | `/{type}/{id}/comments` | ❌ | Lista comentários (`posts` ou `recipes`) |
| `POST` | `/{type}/{id}/comments` | ✅ | Cria comentário |
| `GET` | `/comments/{id}` | ❌ | Detalhe do comentário |
| `PUT/PATCH` | `/comments/{id}` | ✅ | Atualiza comentário |
| `DELETE` | `/comments/{id}` | ✅ | Remove comentário |

### Avaliações
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `GET` | `/{type}/{id}/ratings` | ❌ | Lista avaliações |
| `POST` | `/{type}/{id}/ratings` | ✅ | Cria ou atualiza avaliação do usuário |
| `GET` | `/{type}/{id}/ratings/user` | ✅ | Avaliação do usuário autenticado |
| `DELETE` | `/ratings/{id}` | ✅ | Remove avaliação |

### Categorias, Tópicos, Dietas, Unidades
Todos seguem o padrão CRUD:

| Recurso | Rota Base |
|---|---|
| Categorias de Post | `/post-categories` |
| Tópicos de Post | `/post-topics` |
| Categorias de Receita | `/recipe-categories` |
| Dietas | `/recipe-diets` |
| Unidades de Medida | `/recipe-units` |

### Empresas, Planos, Assinaturas, Pagamentos
| Recurso | Rota Base |
|---|---|
| Empresas | `/companies` |
| Planos | `/plans` |
| Assinaturas | `/subscriptions` |
| Pagamentos | `/payments` |
| Métodos de Pagamento | `/payment-methods` |

### Outros
| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `POST` | `/contact` | ❌ | Formulário de contato |
| `POST` | `/newsletter` | ❌ | Inscrição na newsletter |
| `DELETE` | `/newsletter/{id}` | ✅ | Cancelamento de inscrição |

---

## Autenticação

A API usa **tokens Bearer** gerados pelo Laravel Sanctum. Para rotas protegidas, inclua o header:

```
Authorization: Bearer {seu_token}
```

O token é retornado no corpo da resposta do login e do registro.

---

## Upload de Imagens

Imagens são enviadas via `multipart/form-data` com o campo `image`. O fluxo é:

1. **Validação** do arquivo (tipos: `jpeg`, `png`, `jpg`, `gif`, `svg`, `webp`; máximo: 2 MB)
2. **Upload para S3** via `CreateImage::uploadOnly()` — ocorre *antes* da transação DB
3. **Registro no banco** dentro de uma transação DB via `createDbRecord()`
4. Em caso de falha na transação, o arquivo já enviado ao S3 é **deletado automaticamente** (rollback de arquivo)

URLs de imagens retornadas são **URLs temporárias assinadas** do S3, válidas por 15 minutos, com cache de 5 minutos no Redis.

---

## Busca Full-Text

A busca é gerenciada pelo **Meilisearch** via Laravel Scout. Quando um termo de busca é passado, o `BaseListService` utiliza o driver Meilisearch com filtros e ordenação customizados. Sem termo de busca, é executada uma query SQL tradicional com Eloquent.

**Índices configurados:**

- `posts` — busca em `title`, `summary`, `content`, `author`, `topics`, `category_name`
- `recipes` — busca em `title`, `description`, `category_name`, `author`, `diet_names`, `ingredients`

Para reindexar:
```bash
php artisan scout:import "App\Models\Post"
php artisan scout:import "App\Models\Recipe"
```

---

## Filas e Notificações

Todas as notificações por e-mail implementam `ShouldQueue` e são processadas assincronamente via Redis.

**Notificações existentes:**

| Evento | Notificação |
|---|---|
| Usuário criado | `CreatedUser` — boas-vindas |
| Usuário deletado | `DeletedUser` — confirmação |
| Assinatura de plano | `SubscribedToPlan` |
| Assinatura expirada | `PlanSubscriptionExpired` |
| Nova fatura | `PaymentCreated` |
| Pagamento confirmado | `PaymentPaidNotification` |
| Formulário de contato | `CustomerContactNotification` |
| Inscrição newsletter | `CreateNewsletterCustomerNotification` |
| Cancelamento newsletter | `DeleteNewsletterCustomerNotification` |
| Reset de senha | `PasswordResetNotification` (link para o frontend) |

Para iniciar o worker de filas:
```bash
php artisan queue:work redis --tries=3
```

---

## Cache

O cache utiliza Redis com **cache por tags** (`Cache::tags([...])`), permitindo invalidação granular.

| Tag | Escopo |
|---|---|
| `comments:{type}:{id}` | Comentários de um recurso específico |
| `ratings:{type}:{id}` | Avaliações de um recurso específico |
| `post_categories` | Lista de categorias de post |
| `recipe_categories` | Lista de categorias de receita |
| `post_model.{id}` | Detalhe de um post (1 hora) |
| `recipe_model.{id}` | Detalhe de uma receita (1 hora) |

O cache é **flushed automaticamente** após operações de escrita nos respectivos controllers/services.

---

## Planos e Limites

O middleware `CheckPlanLimit` intercepta requisições de criação de `posts` e `recipes`, verificando se a empresa do usuário autenticado possui uma assinatura ativa e se o limite mensal do plano foi atingido.

```
Rota POST /recipes -> middleware:check.plan.limit:recipe -> controller
```

---

## Testes

O projeto utiliza **PHPUnit** com Feature Tests, padrão **AAA** e `RefreshDatabase`.

```bash
# Executar todos os testes
php artisan test

# Executar com cobertura
php artisan test --coverage

# Executar um arquivo específico
php artisan test tests/Feature/Auth/LoginTest.php
```

**Convenção de nomenclatura:** `Validar_AcaoDescritiva_ResultadoEsperado` (ex: `Validar_LoginComCredenciaisCorretas_Sucesso`)

---

## Deploy com Docker

O `docker-compose.yml` define os seguintes serviços para produção:

| Serviço | Imagem | Descrição |
|---|---|---|
| `app` | `temperinho-app:prod` | Aplicação Laravel (PHP-FPM) |
| `nginx` | `nginx:alpine` | Proxy reverso com SSL |
| `db` | `postgres:15-alpine` | Banco de dados PostgreSQL |
| `meilisearch` | `getmeili/meilisearch:latest` | Motor de busca |
| `redis` | `redis:alpine` | Cache e filas |
| `backup` | `postgres:15-alpine` | Backup automático com `pg_dump` |
| `certbot` | `certbot/certbot` | SSL via Let's Encrypt |

```bash
# Build e start em produção
docker compose up -d --build

# Executar migrações em produção
docker compose exec app php artisan migrate --force

# Visualizar logs
docker compose logs -f app
```

**Volumes persistentes:**
- `temperinho-pgdata` — dados do PostgreSQL
- `temperinho-backups` — dumps de backup
- `storage_prod` — storage da aplicação Laravel
- `meilidata_prod` — índices do Meilisearch

---

## Monitoramento

Erros em produção são capturados pelo **Sentry** via `sentry/sentry-laravel`. Configure `LOG_SENTRY_DSN` no `.env`.

O stack de log padrão inclui os canais `stack` + `sentry`, com nível configurável via `LOG_LEVEL`.

---

## Rate Limiting

Dois limitadores de taxa são configurados no `AppServiceProvider`:

| Limitador | Limite | Critério |
|---|---|---|
| `api` | 60 req/min | por `user_id` ou IP |
| `auth` | 5 req/min | por IP |

---

## Segurança

O middleware `SecureHeaders` adiciona automaticamente os seguintes headers HTTP em todas as respostas:

- `Strict-Transport-Security`
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- Remove `X-Powered-By`

Senhas armazenadas com `bcrypt` (12 rounds configurável via `BCRYPT_ROUNDS`).

---

## Licença

Distribuído sob licença MIT. Consulte `LICENSE` para mais informações.
