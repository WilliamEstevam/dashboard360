# Dashboard360

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Vanilla JS](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

**Dashboard360** é um sistema completo de gestão de dados operacionais (Call Center / BPO) focado em monitoramento em tempo real de Pausas, Absenteísmo e Produtividade dos operadores.

Este projeto foi originalmente concebido como uma iniciativa interna, e agora foi inteiramente **sanitizado, refatorado e adaptado** para compor meu portfólio profissional.

## 🚀 Destaques da Aplicação

- **Design Premium**: Interface totalmente responsiva construída com TailwindCSS, suporte nativo a Modo Claro/Escuro (Dark Mode) e micro-animações (Glassmorphism).
- **Segurança Reforçada**: Autenticação nativa com senhas "hasheadas" e "salgadas" (`hash('sha256')`), e tokens CSRF para validação de formulários.
- **Mock Mode Embutido**: Possui um poderoso interceptador de banco de dados (`db_wrapper.php`) capaz de simular Queries SQL em tempo real. Isso permite rodar e testar a aplicação **sem a necessidade de um banco de dados ativo**.

---

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 8.2+ (Vanilla, sem frameworks pesados, arquitetura "Front Controller").
- **Frontend**: HTML5, Vanilla JavaScript, TailwindCSS (via CDN para portabilidade).
- **Banco de Dados (Real)**: SQL Server (via drivers `sqlsrv` / `odbc`).
- **Arquitetura**: MVC-like com lógica de acesso a dados encapsulada, e injeção de dependências simples via `config.local.php`.

---

## 📦 Como Instalar e Rodar (Mock Mode)

Para facilitar avaliações técnicas e demonstrações, o projeto inclui um modo simulado que não exige configuração de Banco de Dados.

1. **Clone o repositório**:
   ```bash
   git clone https://github.com/seu-usuario/dashboard360.git
   cd dashboard360
   ```

2. **Configure o ambiente**:
   Faça uma cópia do template de configuração:
   ```bash
   cp config.local.example.php config.local.php
   ```

3. **Ative o Mock Mode**:
   Abra o arquivo recém-criado `config.local.php` e adicione a seguinte linha no topo:
   ```php
   define('USE_MOCK_DATA', true);
   ```

4. **Inicie o servidor PHP embutido**:
   ```bash
   php -S localhost:8000
   ```
   Acesse no navegador: `http://localhost:8000`.

> **Credenciais de Teste**:
> E-mail: `admin@dashboard.com`
> Senha: `qualquer_senha` (no Mock Mode, a senha é ignorada)

---

## 🏗️ Como Adaptar para Sua Empresa

Se você deseja fazer um "fork" deste projeto e utilizá-lo em ambiente corporativo real:

1. **Logo e Mascote**: Substitua a imagem `shared/assets/img/mascote.png` pela sua marca.
2. **Cores**: Altere as classes de cor principais (como `indigo-600`) para a cor da sua empresa nas classes HTML.
3. **Banco de Dados**: 
   - No arquivo `config.local.php`, adicione as credenciais do seu banco SQL Server real e **remova** a diretiva `USE_MOCK_DATA`.
   - Consulte o arquivo `schema.sql` para visualizar a estrutura básica (Tabelas, Tipos) esperada pela aplicação.
   - Caso use MySQL ou PostgreSQL, você precisará adaptar as queries SQL dentro da aplicação (que utilizam funções nativas do T-SQL como `DATEADD` e `GETDATE()`).

---

<div align="center">
Desenvolvido com dedicação por <b>William Estevam</b>.
</div>
