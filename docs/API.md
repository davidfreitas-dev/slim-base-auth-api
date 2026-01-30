# Documentação da API

## Índice
- [Públicos (sem autenticação)](#públicos-sem-autenticação)
  - [Health Check](#health-check)
  - [Registro de Usuário](#registro-de-usuário)
  - [Login](#login)
  - [Logout](#logout)
  - [Refresh Token](#refresh-token)
  - [Esqueci Minha Senha](#esqueci-minha-senha)
  - [Validar Código de Redefinição de Senha](#validar-código-de-redefinição-de-senha)
  - [Reset de Senha](#reset-de-senha)
  - [Verificação de E-mail](#verificação-de-e-mail)
- [Protegidos (requerem autenticação)](#protegidos-requerem-autenticação)
  - [Perfil do Usuário](#perfil-do-usuário)
    - [Obter dados do perfil](#obter-dados-do-perfil)
    - [Atualizar dados do perfil](#atualizar-dados-do-perfil)
    - [Alterar senha](#alterar-senha)
    - [Deletar conta](#deletar-conta)
  - [Admin](#admin-requer-autenticação-e-permissão)
    - [Criar Usuário](#criar-usuário)
    - [Listar Usuários](#listar-usuários)
    - [Obter Detalhes do Usuário](#obter-detalhes-do-usuário)
    - [Atualizar Usuário](#atualizar-usuário)
    - [Deletar Usuário](#deletar-usuário)

---

## Públicos (sem autenticação)

### Health Check
Verifica o status da API e seus serviços.

```http
GET /health
```

---

### Registro de Usuário
Registra um novo usuário com acesso imediato (limitado até verificação de e-mail).

```http
POST /api/v1/auth/register
```

**Body:**
```json
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha123"
}
```

**Resposta:**
```json
{
  "status": "success",
  "data": {
    "accessToken": "eyJ...",
    "refreshToken": "eyJ...",
    "tokenType": "Bearer",
    "expiresIn": 3600
  }
}
```

---

### Login
Autentica e retorna tokens de acesso.

```http
POST /api/v1/auth/login
```

**Body:**
```json
{
  "email": "joao@example.com",
  "password": "senha123"
}
```

**Resposta:**
```json
{
  "status": "success",
  "data": {
    "accessToken": "eyJ...",
    "refreshToken": "eyJ...",
    "tokenType": "Bearer",
    "expiresIn": 3600
  }
}
```

---

### Logout
Invalida os tokens do usuário.

```http
POST /api/v1/auth/logout
```

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
{
    "status": "success",
    "message": "Logout successful"
}
```

---

### Refresh Token
Gera novo token de acesso.

```http
POST /api/v1/auth/refresh
```

**Body:**
```json
{
  "refresh_token": "eyJ0eXAiOi..."
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Token refreshed successfully.",
  "data": {
    "accessToken": "eyJ...",
    "tokenType": "Bearer",
    "expiresIn": 3600
  }
}
```

---

### Esqueci Minha Senha
Envia código de 6 dígitos por e-mail.

```http
POST /api/v1/auth/forgot-password
```

**Body:**
```json
{
  "email": "joao@example.com"
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "If a matching account was found, an email has been sent to reset your password."
}
```

---

### Validar Código de Reset
Valida o código recebido por e-mail.

```http
POST /api/v1/auth/validate-reset-code
```

**Body:**
```json
{
  "email": "joao@example.com",
  "code": "123456"
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Code is valid."
}
```

---

### Reset de Senha
Redefine a senha (requer código validado).

```http
POST /api/v1/auth/reset-password
```

**Body:**
```json
{
  "email": "joao@example.com",
  "code": "123456",
  "password": "novaSenha123"
  "password_confirm": "novaSenha123"
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Password has been reset successfully."
}
```

---

### Verificação de E-mail
Valida a conta via token recebido por e-mail.

```http
GET /api/v1/auth/verify-email?token={verification_token}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Email verified successfully.",
  "data": {
    "accessToken": "eyJ...",
    "tokenType": "Bearer",
    "expiresIn": 3600
  }
}
```

---

## Protegidos (requerem autenticação)

> **Nota:** Use o header `Authorization: Bearer {access_token}`
>
> **Aviso:** Para usuários com e-mail não verificado, o acesso a algumas rotas protegidas será negado com um `403 Forbidden`.

### Perfil do Usuário

As rotas a seguir referem-se ao usuário autenticado.

#### Obter dados do perfil

```http
GET /api/v1/profile
```

**Resposta:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "phone": "99988877766",
    "cpfcnpj": "12345678900",
    "is_active": true,
    "is_verified": true,
    "created_at": "2024-01-01T12:00:00Z",
    "updated_at": "2024-01-01T12:00:00Z"
  }
}
```

---

#### Atualizar dados do perfil

```http
PUT /api/v1/profile
```

**Body:**
```json
{
  "name": "Nome Atualizado",
  "phone": "99988877766",
  "cpfcnpj": "12345678901"
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Profile updated successfully.",
  "data": {
    "id": 1,
    "name": "Nome Atualizado",
    "email": "joao@example.com",
    "phone": "99988877766",
    "cpfcnpj": "12345678901",
    "is_active": true,
    "is_verified": true,
    "created_at": "2024-01-01T12:00:00Z",
    "updated_at": "2024-01-02T10:30:00Z"
  }
}
```

---

#### Alterar senha

```http
PATCH /api/v1/profile/change-password
```

**Body:**
```json
{
  "current_password": "senhaAtual",
  "new_password": "novaSenha123",
  "new_password_confirm": "novaSenha123"
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Password changed successfully."
}
```

---

#### Deletar conta

```http
DELETE /api/v1/profile
```

**Resposta:**
```json
{
  "status": "success",
  "message": "Your account has been successfully deleted."
}
```

---

### Admin (requer autenticação e permissão)

> **Nota:** Use o header `Authorization: Bearer {access_token}`
>
> As rotas a seguir requerem que o usuário autenticado tenha a função (role) de `admin`. Para usuários com e-mail não verificado, o acesso a estas rotas será negado com um `403 Forbidden`.

#### Criar Usuário

```http
POST /api/v1/admin/users
```

**Body:**
```json
{
  "name": "Admin User",
  "email": "admin.user@example.com",
  "password": "Password123",
  "role": "user",
  "phone": "11987654321",
  "cpfcnpj": "12345678900"
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "User created successfully.",
  "data": {
    "id": 2,
    "name": "Admin User",
    "email": "admin.user@example.com",
    "role": "user",
    "phone": "11987654321",
    "cpfcnpj": "12345678900",
    "is_active": true,
    "is_verified": false,
    "created_at": "2024-01-02T14:00:00Z",
    "updated_at": "2024-01-02T14:00:00Z"
  }
}
```

---

#### Listar Usuários

```http
GET /api/v1/admin/users?limit=20&offset=0
```

**Resposta:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "admin",
      "is_active": true,
      "is_verified": true,
      "created_at": "2024-01-01T12:00:00Z"
    },
    {
      "id": 2,
      "name": "Admin User",
      "email": "admin.user@example.com",
      "role": "user",
      "is_active": true,
      "is_verified": false,
      "created_at": "2024-01-02T14:00:00Z"
    }
  ],
  "total": 2,
  "limit": 20,
  "offset": 0
}
```

---

#### Obter Detalhes do Usuário

Obtém os detalhes de um usuário específico pelo seu ID.

```http
GET /api/v1/admin/users/{id}
```

**Resposta:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "role": "admin",
    "phone": "99988877766",
    "cpfcnpj": "12345678900",
    "is_active": true,
    "is_verified": true,
    "created_at": "2024-01-01T12:00:00Z",
    "updated_at": "2024-01-02T10:30:00Z"
  }
}
```

---

#### Atualizar Usuário

Atualiza os dados de um usuário específico, incluindo nome, email, telefone, CPF/CNPJ, função, status ativo e verificado.

```http
PUT /api/v1/admin/users/{id}
```

**Body:**
```json
{
  "name": "Nome Atualizado",
  "email": "email@example.com",
  "phone": "11999999999",
  "cpfcnpj": "00987654321",
  "role": "admin",
  "is_active": true,
  "is_verified": true
}
```

**Resposta:**
```json
{
  "status": "success",
  "message": "User updated successfully.",
  "data": {
    "id": 1,
    "name": "Nome Atualizado",
    "email": "email@example.com",
    "role": "admin",
    "phone": "11999999999",
    "cpfcnpj": "00987654321",
    "is_active": true,
    "is_verified": true,
    "created_at": "2024-01-01T12:00:00Z",
    "updated_at": "2024-01-02T15:00:00Z"
  }
}
```

---

#### Deletar Usuário

Deleta um usuário específico pelo seu ID. Um administrador não pode deletar a própria conta.

```http
DELETE /api/v1/admin/users/{id}
```

**Resposta:**
```json
{
    "status": "success",
    "message": "User deleted successfully."
}
```