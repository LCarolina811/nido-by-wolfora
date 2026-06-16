-- ============================================================
-- NIDO BY WOLFORA - Sistema de Gestión Financiera Personal
-- Base de Datos: nido_finanzas
-- Versión: 1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS nido_finanzas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE nido_finanzas;

-- ============================================================
-- TABLA: usuarios
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(50)     NOT NULL,
    email           VARCHAR(100)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    avatar_color    VARCHAR(7)      NOT NULL DEFAULT '#6C63FF',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: categorias
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100)                NOT NULL,
    icono   VARCHAR(10)                 NOT NULL DEFAULT '📦',
    color   VARCHAR(7)                  NOT NULL DEFAULT '#6C63FF',
    tipo    ENUM('gasto','ingreso')     NOT NULL DEFAULT 'gasto',
    activo  TINYINT(1)                  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: periodos
-- Representa cada mes/año del historial financiero
-- ============================================================
CREATE TABLE IF NOT EXISTS periodos (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    anio    SMALLINT UNSIGNED   NOT NULL,
    mes     TINYINT UNSIGNED    NOT NULL CHECK (mes BETWEEN 1 AND 12),
    activo  TINYINT(1)          NOT NULL DEFAULT 0,
    cerrado TINYINT(1)          NOT NULL DEFAULT 0,
    UNIQUE KEY uk_periodo (anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: gastos
-- ============================================================
CREATE TABLE IF NOT EXISTS gastos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concepto            VARCHAR(200)                                        NOT NULL,
    categoria_id        INT UNSIGNED                                        NOT NULL,
    valor               DECIMAL(12,2)                                       NOT NULL,
    responsable         ENUM('carolina','javier','compartido')              NOT NULL,
    tipo                ENUM('fijo','cuotas','variable')                    NOT NULL,
    estado              ENUM('pendiente','pagado')                          NOT NULL DEFAULT 'pendiente',
    fecha_pago          DATE                                                NOT NULL,
    quincena            ENUM('primera','segunda')                           NOT NULL,
    periodo_id          INT UNSIGNED                                        NOT NULL,
    usuario_creador_id  INT UNSIGNED                                        NOT NULL,
    gasto_padre_id      INT UNSIGNED                                        NULL DEFAULT NULL,
    notas               TEXT                                                NULL,
    created_at          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_gastos_categoria     FOREIGN KEY (categoria_id)       REFERENCES categorias (id),
    CONSTRAINT fk_gastos_periodo       FOREIGN KEY (periodo_id)         REFERENCES periodos (id),
    CONSTRAINT fk_gastos_usuario       FOREIGN KEY (usuario_creador_id) REFERENCES usuarios (id),
    CONSTRAINT fk_gastos_padre         FOREIGN KEY (gasto_padre_id)     REFERENCES gastos (id) ON DELETE SET NULL,

    INDEX idx_gastos_periodo        (periodo_id),
    INDEX idx_gastos_responsable    (responsable),
    INDEX idx_gastos_tipo           (tipo),
    INDEX idx_gastos_estado         (estado),
    INDEX idx_gastos_quincena       (quincena),
    INDEX idx_gastos_fecha          (fecha_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: gastos_cuotas
-- Control del contador y estado de gastos en cuotas
-- ============================================================
CREATE TABLE IF NOT EXISTS gastos_cuotas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gasto_id            INT UNSIGNED    NOT NULL,
    gasto_origen_id     INT UNSIGNED    NOT NULL,
    cuota_numero        TINYINT UNSIGNED NOT NULL,
    total_cuotas        TINYINT UNSIGNED NOT NULL,
    valor_cuota         DECIMAL(12,2)   NOT NULL,

    CONSTRAINT fk_cuotas_gasto      FOREIGN KEY (gasto_id)        REFERENCES gastos (id) ON DELETE CASCADE,
    CONSTRAINT fk_cuotas_origen     FOREIGN KEY (gasto_origen_id) REFERENCES gastos (id),

    INDEX idx_cuotas_origen (gasto_origen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: ingresos
-- ============================================================
CREATE TABLE IF NOT EXISTS ingresos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concepto            VARCHAR(200)                    NOT NULL,
    categoria_id        INT UNSIGNED                    NOT NULL,
    valor               DECIMAL(12,2)                   NOT NULL,
    usuario_id          INT UNSIGNED                    NOT NULL,
    tipo                ENUM('fijo','variable')         NOT NULL,
    fecha               DATE                            NOT NULL,
    periodo_id          INT UNSIGNED                    NOT NULL,
    ingreso_padre_id    INT UNSIGNED                    NULL DEFAULT NULL,
    notas               TEXT                            NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_ingresos_categoria    FOREIGN KEY (categoria_id)    REFERENCES categorias (id),
    CONSTRAINT fk_ingresos_usuario      FOREIGN KEY (usuario_id)      REFERENCES usuarios (id),
    CONSTRAINT fk_ingresos_periodo      FOREIGN KEY (periodo_id)      REFERENCES periodos (id),
    CONSTRAINT fk_ingresos_padre        FOREIGN KEY (ingreso_padre_id) REFERENCES ingresos (id) ON DELETE SET NULL,

    INDEX idx_ingresos_periodo  (periodo_id),
    INDEX idx_ingresos_usuario  (usuario_id),
    INDEX idx_ingresos_tipo     (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: presupuestos
-- ============================================================
CREATE TABLE IF NOT EXISTS presupuestos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id    INT UNSIGNED                                    NOT NULL,
    monto           DECIMAL(12,2)                                   NOT NULL,
    periodo_id      INT UNSIGNED                                    NOT NULL,
    responsable     ENUM('carolina','javier','compartido')          NOT NULL DEFAULT 'compartido',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_presupuesto (categoria_id, periodo_id, responsable),

    CONSTRAINT fk_presupuestos_categoria    FOREIGN KEY (categoria_id)  REFERENCES categorias (id),
    CONSTRAINT fk_presupuestos_periodo      FOREIGN KEY (periodo_id)    REFERENCES periodos (id),

    INDEX idx_presupuestos_periodo (periodo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: ahorros
-- Metas de ahorro (no ligadas a períodos)
-- ============================================================
CREATE TABLE IF NOT EXISTS ahorros (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(100)    NOT NULL,
    monto_objetivo      DECIMAL(12,2)   NOT NULL,
    monto_acumulado     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    fecha_objetivo      DATE            NULL,
    icono               VARCHAR(10)     NOT NULL DEFAULT '🏦',
    color               VARCHAR(7)      NOT NULL DEFAULT '#6C63FF',
    activo              TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: ahorros_movimientos
-- Historial de aportes y retiros por meta
-- ============================================================
CREATE TABLE IF NOT EXISTS ahorros_movimientos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ahorro_id   INT UNSIGNED                    NOT NULL,
    usuario_id  INT UNSIGNED                    NOT NULL,
    tipo        ENUM('aporte','retiro')         NOT NULL,
    monto       DECIMAL(12,2)                   NOT NULL,
    descripcion VARCHAR(200)                    NULL,
    fecha       DATE                            NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mov_ahorro   FOREIGN KEY (ahorro_id)  REFERENCES ahorros (id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id) REFERENCES usuarios (id),

    INDEX idx_mov_ahorro    (ahorro_id),
    INDEX idx_mov_fecha     (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Usuarios (contraseñas: Carolina = "carolina2025", Javier = "javier2025")
-- Hashes generados con password_hash() de PHP usando PASSWORD_BCRYPT
INSERT INTO usuarios (nombre, email, password_hash, avatar_color) VALUES
('Carolina', 'carolina@nido.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '#FF6B9D'),
('Javier',   'javier@nido.local',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '#6C63FF');

-- Categorías de Gastos
INSERT INTO categorias (nombre, icono, color, tipo) VALUES
('Arriendo',        '🏠', '#FF6B6B', 'gasto'),
('Servicios',       '💡', '#FFB347', 'gasto'),
('Mercado',         '🛒', '#51CF66', 'gasto'),
('Transporte',      '🚗', '#339AF0', 'gasto'),
('Salud',           '🏥', '#FF6B9D', 'gasto'),
('Mascotas',        '🐾', '#A855F7', 'gasto'),
('Entretenimiento', '🎬', '#F59E0B', 'gasto'),
('Restaurantes',    '🍽️', '#EF4444', 'gasto'),
('Ropa',            '👗', '#EC4899', 'gasto'),
('Tecnología',      '💻', '#3B82F6', 'gasto'),
('Hogar',           '🛋️', '#8B5CF6', 'gasto'),
('Educación',       '📚', '#06B6D4', 'gasto'),
('Viajes',          '✈️', '#10B981', 'gasto'),
('Deudas',          '💳', '#DC2626', 'gasto'),
('Otros gastos',    '📦', '#6B7280', 'gasto');

-- Categorías de Ingresos
INSERT INTO categorias (nombre, icono, color, tipo) VALUES
('Salario',         '💰', '#10B981', 'ingreso'),
('Comisiones',      '📈', '#3B82F6', 'ingreso'),
('Bonificaciones',  '🎁', '#F59E0B', 'ingreso'),
('Freelance',       '💼', '#8B5CF6', 'ingreso'),
('Otros ingresos',  '➕', '#6B7280', 'ingreso');

-- Período inicial: Junio 2026 (mes actual)
INSERT INTO periodos (anio, mes, activo, cerrado) VALUES
(2026, 1,  0, 1),
(2026, 2,  0, 1),
(2026, 3,  0, 1),
(2026, 4,  0, 1),
(2026, 5,  0, 1),
(2026, 6,  1, 0);

-- Metas de ahorro iniciales
INSERT INTO ahorros (nombre, monto_objetivo, monto_acumulado, fecha_objetivo, icono, color) VALUES
('Fondo de Emergencia',  5000000.00, 0.00, '2026-12-31', '🛡️', '#10B981'),
('Viaje',               10000000.00, 0.00, '2027-06-30', '✈️', '#3B82F6'),
('Emma',                 2000000.00, 0.00, NULL,          '🐾', '#A855F7'),
('Tecnología',           1500000.00, 0.00, '2026-12-31', '💻', '#6C63FF'),
('Casa',                50000000.00, 0.00, '2030-01-01', '🏠', '#F59E0B');
