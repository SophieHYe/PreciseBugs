DO LANGUAGE plpgsql
$OUTER$
DECLARE
    my_schema pg_catalog.TEXT := pg_catalog.quote_ident(pg_catalog.current_schema());
    old_path pg_catalog.TEXT := pg_catalog.current_setting('search_path');
BEGIN

-- for safety, transiently set search_path to just pg_catalog+pg_temp
PERFORM pg_catalog.set_config('search_path', 'pg_catalog, pg_temp', true);


PERFORM 1
    FROM pg_catalog.pg_type AS t JOIN pg_catalog.pg_roles AS r ON (r.oid = t.typowner)
    WHERE r.rolsuper
        AND t.typnamespace = 'aiven_extras'::regnamespace
        AND t.typname = 'aiven_pg_subscription';
IF NOT FOUND THEN
    CREATE TYPE aiven_extras.aiven_pg_subscription AS (
        subdbid OID,
        subname NAME,
        subowner OID,
        subenabled BOOLEAN,
        subconninfo TEXT,
        subslotname NAME,
        subsynccommit TEXT,
        subpublications TEXT[]
    );
END IF;

PERFORM 1
    FROM pg_catalog.pg_type AS t JOIN pg_catalog.pg_roles AS r ON (r.oid = t.typowner)
    WHERE r.rolsuper
        AND typnamespace = 'aiven_extras'::regnamespace
        AND typname = 'aiven_pg_stat_replication';
IF NOT FOUND THEN
    CREATE TYPE aiven_extras.aiven_pg_stat_replication AS (
        pid INT,
        usesysid OID,
        usename NAME,
        application_name TEXT,
        client_addr INET,
        client_hostname TEXT,
        client_port INT,
        backend_start TIMESTAMP WITH TIME ZONE,
        backend_xmin XID,
        state TEXT,
        sent_lsn PG_LSN,
        write_lsn PG_LSN,
        flush_lsn PG_LSN,
        replay_lsn PG_LSN,
        write_lag INTERVAL,
        flush_lag INTERVAL,
        replay_lag INTERVAL,
        sync_priority INTEGER,
        sync_state TEXT
    );
END IF;


DROP FUNCTION IF EXISTS aiven_extras.dblink_record_execute(TEXT, TEXT);
CREATE FUNCTION aiven_extras.dblink_record_execute(TEXT, TEXT)
RETURNS SETOF record LANGUAGE c
PARALLEL RESTRICTED STRICT
AS '$libdir/dblink', $$dblink_record$$;


DROP FUNCTION IF EXISTS aiven_extras.dblink_slot_create_or_drop(TEXT, TEXT, TEXT);
CREATE FUNCTION aiven_extras.dblink_slot_create_or_drop(
    arg_connection_string TEXT,
    arg_slot_name TEXT,
    arg_action TEXT
)
RETURNS VOID LANGUAGE plpgsql
SET search_path = pg_catalog, aiven_extras
AS $$
DECLARE
    l_clear_search_path TEXT := 'SET search_path TO pg_catalog, pg_temp;';
    l_slot_existence_query TEXT := pg_catalog.format('SELECT TRUE FROM pg_catalog.pg_replication_slots WHERE slot_name OPERATOR(pg_catalog.=) %L', arg_slot_name);
    l_slot_action_query TEXT;
    l_slot_exists BOOLEAN;
BEGIN
    SELECT res INTO l_slot_exists
        FROM aiven_extras.dblink_record_execute(
                arg_connection_string,
                l_clear_search_path || l_slot_existence_query
            ) AS d (res BOOLEAN);
    IF arg_action = 'create' AND l_slot_exists IS NOT TRUE THEN
        l_slot_action_query := pg_catalog.format('SELECT TRUE FROM pg_catalog.pg_create_logical_replication_slot(%L, %L, FALSE)', arg_slot_name, 'pgoutput');
    ELSIF arg_action = 'drop' AND l_slot_exists IS TRUE THEN
        l_slot_action_query := pg_catalog.format('SELECT TRUE FROM pg_catalog.pg_drop_replication_slot(%L)', arg_slot_name);
    END IF;
    IF l_slot_action_query IS NOT NULL THEN
        PERFORM 1
            FROM aiven_extras.dblink_record_execute(
                    arg_connection_string,
                    l_clear_search_path || l_slot_action_query
                ) AS d (res BOOLEAN);
    END IF;
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.pg_create_subscription(TEXT, TEXT, TEXT, TEXT, BOOLEAN, BOOLEAN);
CREATE FUNCTION aiven_extras.pg_create_subscription(
    arg_subscription_name TEXT,
    arg_connection_string TEXT,
    arg_publication_name TEXT,
    arg_slot_name TEXT,
    arg_slot_create BOOLEAN = FALSE,
    arg_copy_data BOOLEAN = TRUE
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    IF (arg_slot_create IS TRUE) THEN
        PERFORM aiven_extras.dblink_slot_create_or_drop(arg_connection_string, arg_slot_name, 'create');
    END IF;
    EXECUTE pg_catalog.format(
        'CREATE SUBSCRIPTION %I connection %L publication %I WITH (slot_name=%L, create_slot=FALSE, copy_data=%s)',
        arg_subscription_name, arg_connection_string, arg_publication_name, arg_slot_name, arg_copy_data::TEXT);
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.pg_alter_subscription_disable(TEXT);
CREATE FUNCTION aiven_extras.pg_alter_subscription_disable(
    arg_subscription_name TEXT
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    EXECUTE pg_catalog.format('ALTER SUBSCRIPTION %I DISABLE', arg_subscription_name);
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.pg_alter_subscription_enable(TEXT);
CREATE FUNCTION aiven_extras.pg_alter_subscription_enable(
    arg_subscription_name TEXT
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    EXECUTE pg_catalog.format('ALTER SUBSCRIPTION %I ENABLE', arg_subscription_name);
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.pg_alter_subscription_refresh_publication(TEXT, BOOLEAN);
CREATE FUNCTION aiven_extras.pg_alter_subscription_refresh_publication(
    arg_subscription_name TEXT,
    arg_copy_data BOOLEAN = TRUE
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    PERFORM aiven_extras.dblink_record_execute(
        pg_catalog.format('user=%L dbname=%L port=%L', current_user, pg_catalog.current_database(), (SELECT setting FROM pg_catalog.pg_settings WHERE name = 'port')),
        pg_catalog.format('ALTER SUBSCRIPTION %I REFRESH PUBLICATION WITH (copy_data=%s)', arg_subscription_name, arg_copy_data::TEXT)
    );
END;
$$;

DROP FUNCTION IF EXISTS aiven_extras.pg_drop_subscription(TEXT);
CREATE FUNCTION aiven_extras.pg_drop_subscription(
    arg_subscription_name TEXT
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
DECLARE
    l_slot_name TEXT;
    l_subconninfo TEXT;
BEGIN
    SELECT subslotname, subconninfo
        INTO l_slot_name, l_subconninfo
        FROM pg_catalog.pg_subscription
        WHERE subname = arg_subscription_name;
    IF l_slot_name IS NULL AND l_subconninfo IS NULL THEN
        RAISE EXCEPTION 'No subscription found for name: %', arg_subscription_name;
    END IF;
    EXECUTE pg_catalog.format('ALTER SUBSCRIPTION %I DISABLE', arg_subscription_name);
    EXECUTE pg_catalog.format('ALTER SUBSCRIPTION %I SET (slot_name = NONE)', arg_subscription_name);
    EXECUTE pg_catalog.format('DROP SUBSCRIPTION %I', arg_subscription_name);
    PERFORM aiven_extras.dblink_slot_create_or_drop(l_subconninfo, l_slot_name, 'drop');
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.pg_create_publication_for_all_tables(TEXT, TEXT);
CREATE FUNCTION aiven_extras.pg_create_publication_for_all_tables(
    arg_publication_name TEXT,
    arg_publish TEXT
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    EXECUTE pg_catalog.format('CREATE PUBLICATION %I FOR ALL TABLES WITH (publish = %I)', arg_publication_name, arg_publish);
    EXECUTE pg_catalog.format('ALTER PUBLICATION %I OWNER TO %I', arg_publication_name, session_user);
END;
$$;

DROP FUNCTION IF EXISTS aiven_extras.pg_list_all_subscriptions();
CREATE FUNCTION aiven_extras.pg_list_all_subscriptions()
RETURNS SETOF aiven_extras.aiven_pg_subscription LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN QUERY
        SELECT subdbid, subname, subowner, subenabled, subconninfo, subslotname, subsynccommit, subpublications
            FROM pg_catalog.pg_subscription;
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.session_replication_role(TEXT);
CREATE FUNCTION aiven_extras.session_replication_role(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('session_replication_role', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.auto_explain_load();
CREATE FUNCTION aiven_extras.auto_explain_load()
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    LOAD 'auto_explain';
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_analyze(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_analyze(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_analyze', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_format(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_format(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_format', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_min_duration(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_min_duration(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_min_duration', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_timing(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_timing(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_timing', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_buffers(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_buffers(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_buffers', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_verbose(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_verbose(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_verbose', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_auto_explain_log_nested_statements(TEXT);
CREATE FUNCTION aiven_extras.set_auto_explain_log_nested_statements(
    arg_parameter TEXT
)
RETURNS TEXT LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN pg_catalog.set_config('auto_explain.log_nested_statements', arg_parameter, false);
END
$$;


DROP FUNCTION IF EXISTS aiven_extras.claim_public_schema_ownership();
CREATE FUNCTION aiven_extras.claim_public_schema_ownership()
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    EXECUTE pg_catalog.format('ALTER SCHEMA public OWNER TO %I', session_user);
END;
$$;


-- Temporarily clear out the view so we can replace the function behind it
CREATE OR REPLACE VIEW aiven_extras.pg_stat_replication AS
    SELECT
        NULL::INT AS pid,
        NULL::OID AS usesysid,
        NULL::NAME AS usename,
        NULL::TEXT AS application_name,
        NULL::INET AS client_addr,
        NULL::TEXT AS client_hostname,
        NULL::INT AS client_port,
        NULL::TIMESTAMPTZ AS backend_start,
        NULL::XID AS backend_xmin,
        NULL::TEXT AS state,
        NULL::PG_LSN AS sent_lsn,
        NULL::PG_LSN AS write_lsn,
        NULL::PG_LSN AS flush_lsn,
        NULL::PG_LSN AS replay_lsn,
        NULL::INTERVAL AS write_lag,
        NULL::INTERVAL AS flush_lag,
        NULL::INTERVAL AS replay_lag,
        NULL::INTEGER AS sync_priority,
        NULL::TEXT AS sync_state;


DROP FUNCTION IF EXISTS aiven_extras.pg_stat_replication_list();
CREATE FUNCTION aiven_extras.pg_stat_replication_list()
RETURNS SETOF aiven_extras.aiven_pg_stat_replication LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    RETURN QUERY
        SELECT  pid, usesysid, usename, application_name, client_addr, client_hostname, client_port,
                backend_start, backend_xmin, state, sent_lsn, write_lsn, flush_lsn, replay_lsn, write_lag,
                flush_lag, replay_lag, sync_priority, sync_state
            FROM pg_catalog.pg_stat_replication
            WHERE usename = session_user;
END;
$$;


CREATE OR REPLACE VIEW aiven_extras.pg_stat_replication AS
        SELECT  pid, usesysid, usename, application_name, client_addr, client_hostname, client_port,
                backend_start, backend_xmin, state, sent_lsn, write_lsn, flush_lsn, replay_lsn, write_lag,
                flush_lag, replay_lag, sync_priority, sync_state
            FROM aiven_extras.pg_stat_replication_list();


DROP FUNCTION IF EXISTS aiven_extras.pg_create_publication(TEXT, TEXT, VARIADIC TEXT[]);
CREATE FUNCTION aiven_extras.pg_create_publication(
    arg_publication_name TEXT,
    arg_publish TEXT,
    VARIADIC arg_tables TEXT[] DEFAULT ARRAY[]::TEXT[]
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
DECLARE
  l_ident TEXT;
  l_table_count INT;
  l_tables_command TEXT;
  l_parsed_ident TEXT[];
  l_parsed_arg_tables TEXT[];
BEGIN
    l_table_count = array_length(arg_tables, 1);
    IF l_table_count >= 1
    THEN
        l_parsed_arg_tables = ARRAY[]::TEXT[];
        l_tables_command = 'CREATE PUBLICATION %I FOR TABLE ';
        FOREACH l_ident IN ARRAY arg_tables LOOP
            l_parsed_ident = parse_ident(l_ident);
            ASSERT array_length(l_parsed_ident, 1) <= 2, 'Only simple table names or tables qualified with schema names allowed';
            -- Make sure we pass in a simple list of identifiers, so separate the tables from parent schemas
            IF array_length(l_parsed_ident, 1) = 2
            THEN
                l_tables_command = l_tables_command || '%I.%I, ';
            ELSE
                l_tables_command = l_tables_command || '%I, ';
            END IF;
            l_parsed_arg_tables = l_parsed_arg_tables || l_parsed_ident;
        END LOOP;
        -- Remove trailing comma and whitespace, add the rest
        l_tables_command = left(l_tables_command, -2) || ' WITH (publish = %I)';
        EXECUTE format(l_tables_command, VARIADIC array[arg_publication_name] || l_parsed_arg_tables || arg_publish);
    ELSE
        EXECUTE format('CREATE PUBLICATION %I WITH (publish = %I)', arg_publication_name, arg_publish);
    END IF;
    EXECUTE format('ALTER PUBLICATION %I OWNER TO %I', arg_publication_name, session_user);
END;
$$;


DROP FUNCTION IF EXISTS aiven_extras.set_pgaudit_parameter(TEXT, TEXT, TEXT);
CREATE FUNCTION aiven_extras.set_pgaudit_parameter(
    arg_parameter TEXT,
    arg_database TEXT,
    arg_value TEXT
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    IF COALESCE(
        (SELECT usesuper
            FROM pg_catalog.pg_database d
                JOIN pg_catalog.pg_user u
                    ON (u.usesysid = d.datdba)
                WHERE d.datname = arg_database
                LIMIT 1
        ),
        TRUE
    ) THEN
        RAISE EXCEPTION 'Invalid database: %', arg_database;
    ELSIF arg_parameter NOT IN (
        'log',
        'log_catalog',
        'log_max_string_length',
        'log_nested_statements',
        'log_parameter',
        'log_relation',
        'log_statement',
        'log_statement_once'
    ) THEN
        RAISE EXCEPTION 'Invalid parameter: %', arg_parameter;
    END IF;

    EXECUTE format('ALTER DATABASE %I SET pgaudit.%I = %L',
        arg_database,
        arg_parameter,
        arg_value
    );
END;
$$;

DROP FUNCTION IF EXISTS aiven_extras.set_pgaudit_role_parameter(TEXT, TEXT, TEXT);
CREATE FUNCTION aiven_extras.set_pgaudit_role_parameter(
    arg_parameter TEXT,
    arg_role TEXT,
    arg_value TEXT
)
RETURNS VOID LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, aiven_extras
AS $$
BEGIN
    IF COALESCE(
        (SELECT rolsuper
            FROM pg_catalog.pg_roles
                WHERE rolname = arg_role
                LIMIT 1
        ),
        FALSE
    ) THEN
        RAISE EXCEPTION 'Configuring superuser roles not allowed: %', arg_role;
    ELSIF arg_parameter NOT IN (
        'log',
        'log_catalog',
        'log_max_string_length',
        'log_nested_statements',
        'log_parameter',
        'log_relation',
        'log_statement',
        'log_statement_once'
    ) THEN
        RAISE EXCEPTION 'Invalid parameter: %', arg_parameter;
    END IF;

    EXECUTE format('ALTER ROLE %I SET pgaudit.%I = %L',
        arg_role,
        arg_parameter,
        arg_value
    );
END;
$$;

DROP FUNCTION IF EXISTS aiven_extras.explain_statement(TEXT);
CREATE FUNCTION aiven_extras.explain_statement(
    arg_query TEXT,
    OUT execution_plan JSON
)
RETURNS SETOF JSON
RETURNS NULL ON NULL INPUT
LANGUAGE plpgsql
-- This is needed because otherwise the executing user would need to have the
-- SELECT privilege on all tables that are part of the plan.
SECURITY DEFINER
-- We don't want to force users to change statements (e.g. schema-prefix all
-- tables in the query), so this intentionally does not specifiy a search_path.
-- Still, this will not help with users having custom search paths.
AS $$
DECLARE
    curs REFCURSOR;
    plan JSON;
BEGIN
    OPEN curs FOR EXECUTE pg_catalog.concat('EXPLAIN (FORMAT JSON) ', arg_query);
    FETCH curs INTO plan;
    CLOSE curs;
    RETURN QUERY SELECT plan;
END;
$$;


-- THIS LINE ALWAYS NEEDS TO BE EXECUTED LAST IN FILE
PERFORM pg_catalog.set_config('search_path', old_path, true);
-- NO MORE CODE AFTER THIS

END;
$OUTER$;

-- standby slots functions
DROP FUNCTION IF EXISTS aiven_extras.pg_create_logical_replication_slot_on_standby(name, name, boolean, boolean);
CREATE FUNCTION aiven_extras.pg_create_logical_replication_slot_on_standby(
	slot_name name,
	plugin name,
	temporary boolean DEFAULT false,
	twophase boolean DEFAULT false,
	OUT slot_name name, OUT lsn pg_lsn)
AS 'MODULE_PATHNAME', 'standby_slot_create'
LANGUAGE C;
