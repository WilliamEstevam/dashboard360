-- schema.sql
-- Este é um esquema básico para ajudar na criação das tabelas para o Dashboard360.
-- O banco original era SQL Server. Adapte os tipos conforme o seu banco de dados (MySQL, PostgreSQL, etc).

CREATE SCHEMA consultoria;
GO

CREATE TABLE consultoria.tbl_usuarios_v2 (
    id INT IDENTITY(1,1) PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    nome_completo VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NULL,
    senha_hash VARCHAR(255) NULL,
    salt VARCHAR(255) NULL,
    cargo VARCHAR(100) NULL,
    matricula VARCHAR(100) NULL,
    precisa_mudar_senha BIT DEFAULT 0,
    foto_path VARCHAR(255) NULL,
    token_convite VARCHAR(255) NULL
);
GO

-- Adicione outras tabelas conforme necessário pelas páginas do sistema:
-- TBL_PERM_RELATORIO_TU
-- tbl_temp_estado_atual
-- E outras que a sua versão exigir.
