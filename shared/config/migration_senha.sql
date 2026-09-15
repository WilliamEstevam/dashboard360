ALTER TABLE consultoria.tbl_usuarios_v2
    ADD salt VARCHAR(32) NULL;

ALTER TABLE consultoria.tbl_usuarios_v2
    ADD senha_hash VARCHAR(64) NULL;