-- ============================================
-- REPARO v2 — Base de datos
-- ============================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `reparo_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `reparo_db`;

-- Empresas
CREATE TABLE `empresas` (
  `id_empresa`   INT NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(80) NOT NULL,
  `plan`         ENUM('basico','pro') DEFAULT 'pro',
  `activa`       TINYINT(1) DEFAULT 1,
  `creada_en`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `empresas` (`nombre`,`plan`) VALUES ('Mi Taller Demo','pro');

-- Usuarios
CREATE TABLE `usuarios` (
  `id_usuario`  INT NOT NULL AUTO_INCREMENT,
  `id_empresa`  INT NOT NULL,
  `nombre`      VARCHAR(60) NOT NULL,
  `user`        VARCHAR(40) NOT NULL,
  `pass`        VARCHAR(255) NOT NULL,
  `cargo`       ENUM('Admin','Tecnico') DEFAULT 'Tecnico',
  `activo`      TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_user_empresa` (`user`,`id_empresa`),
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios` (`id_empresa`,`nombre`,`user`,`pass`,`cargo`) VALUES
(1,'Administrador','admin',MD5('admin123'),'Admin'),
(1,'Técnico Demo','tecnico',MD5('tecnico123'),'Tecnico');

-- Marcas
CREATE TABLE `marcas` (
  `id_marca`   INT NOT NULL AUTO_INCREMENT,
  `id_empresa` INT NOT NULL,
  `marca`      VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id_marca`),
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `marcas` (`id_empresa`,`marca`) VALUES
(1,'SAMSUNG'),(1,'IPHONE'),(1,'HUAWEI'),(1,'MOTOROLA'),(1,'XIAOMI'),
(1,'REDMI'),(1,'OPPO'),(1,'LG'),(1,'VIVO'),(1,'NOTEBOOK'),(1,'OTRO');

-- Reparaciones
CREATE TABLE `reparaciones` (
  `id_ingreso`       INT NOT NULL AUTO_INCREMENT,
  `id_empresa`       INT NOT NULL,
  `fecha_ingreso`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `nombre_cliente`   VARCHAR(60) NOT NULL,
  `telefono_cliente` VARCHAR(20) DEFAULT '',
  `rut_cliente`      VARCHAR(20) DEFAULT '',
  `tipo_ingreso`     VARCHAR(30) DEFAULT 'Telefono',
  `marca_ingreso`    VARCHAR(30) DEFAULT '',
  `modelo_ingreso`   VARCHAR(40) DEFAULT '',
  `imei`             VARCHAR(30) DEFAULT '',
  `pass_ingreso`     VARCHAR(30) DEFAULT 'Sin contraseña',
  `daño_ingreso`     VARCHAR(120) DEFAULT '',
  `valor_ingreso`    INT DEFAULT 0,
  `status`           ENUM('Ingresado','En Reparacion','Reparado','Entregado','Garantia') DEFAULT 'Ingresado',
  `obs`              TEXT DEFAULT '',
  `ingresado_por`    VARCHAR(40) DEFAULT '',
  PRIMARY KEY (`id_ingreso`),
  KEY `idx_empresa` (`id_empresa`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historial de estados
CREATE TABLE `historial` (
  `id_historial`   INT NOT NULL AUTO_INCREMENT,
  `id_empresa`     INT NOT NULL,
  `id_reparacion`  INT NOT NULL,
  `status_anterior` VARCHAR(30) DEFAULT '',
  `status_cambio`  VARCHAR(30) NOT NULL,
  `fecha_cambio`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `user`           VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id_historial`),
  FOREIGN KEY (`id_reparacion`) REFERENCES `reparaciones`(`id_ingreso`) ON DELETE CASCADE,
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Observaciones
CREATE TABLE `observaciones` (
  `id_obs`       INT NOT NULL AUTO_INCREMENT,
  `id_empresa`   INT NOT NULL,
  `id_registro`  INT NOT NULL,
  `fecha`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `obs`          TEXT NOT NULL,
  `user`         VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id_obs`),
  FOREIGN KEY (`id_registro`) REFERENCES `reparaciones`(`id_ingreso`) ON DELETE CASCADE,
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5 SERVICIOS DE EJEMPLO
-- ============================================
INSERT INTO `reparaciones`
  (`id_empresa`,`fecha_ingreso`,`nombre_cliente`,`telefono_cliente`,`rut_cliente`,
   `tipo_ingreso`,`marca_ingreso`,`modelo_ingreso`,`imei`,`pass_ingreso`,
   `daño_ingreso`,`valor_ingreso`,`status`,`obs`,`ingresado_por`)
VALUES
(1,'2026-06-18 09:15:00','Javiera Morales','956781234','19.234.567-8',
 'Telefono','Samsung','Galaxy A54','352981234567890','1234',
 'Pantalla rota, no responde al tacto',55000,'En Reparacion',
 'Abono de $20.000 al ingreso','admin'),

(1,'2026-06-19 10:30:00','Rodrigo Espinoza','987654321','15.432.198-k',
 'Telefono','iPhone','13 Pro','359871234567891','Sin contraseña',
 'Cambio de batería, duración menos de 2 horas',45000,'Reparado',
 'Se reemplazó batería original. Listo para retirar.','admin'),

(1,'2026-06-20 11:00:00','Camila Vásquez','934567890','22.876.543-2',
 'Notebook','Asus','VivoBook 15','','Sin contraseña',
 'Formateo y reinstalación de Windows 11',25000,'Ingresado',
 'Viene sin cargador, se deja anotado','tecnico'),

(1,'2026-06-21 14:45:00','Felipe Núñez','945678901','17.654.321-5',
 'Telefono','Motorola','G54 5G','356789123456780','0000',
 'No enciende, posible daño por humedad',15000,'Ingresado',
 'Se hizo revisión inicial, placa con corrosión','tecnico'),

(1,'2026-06-22 08:30:00','Valentina Reyes','912345678','20.123.456-9',
 'Telefono','Redmi','Note 12','351234567890123','Sin contraseña',
 'Puerto de carga dañado, no carga',18000,'Entregado',
 'Se cambió conector de carga tipo C. Entregado.','admin');

-- Historial para cada servicio
INSERT INTO `historial` (`id_empresa`,`id_reparacion`,`status_anterior`,`status_cambio`,`user`) VALUES
(1,1,'','Ingresado','admin'),
(1,1,'Ingresado','En Reparacion','admin'),
(1,2,'','Ingresado','admin'),
(1,2,'Ingresado','Reparado','admin'),
(1,3,'','Ingresado','tecnico'),
(1,4,'','Ingresado','tecnico'),
(1,5,'','Ingresado','admin'),
(1,5,'Ingresado','Entregado','admin');

-- Observaciones para cada servicio
INSERT INTO `observaciones` (`id_empresa`,`id_registro`,`obs`,`user`) VALUES
(1,1,'Pantalla original no disponible en stock, cliente acepta genérica','admin'),
(1,1,'Se inició cambio de pantalla','admin'),
(1,2,'Batería al 71% de salud según diagnóstico','admin'),
(1,2,'Batería reemplazada, equipo funcionando correctamente. Ciclos: 0','admin'),
(1,3,'Cliente dejó clave de Windows anotada en papel dentro del equipo','tecnico'),
(1,4,'Se detectó óxido en conector de batería y zona de carga','tecnico'),
(1,5,'Puerto cambiado con éxito, se comprobó carga al 100%','admin');
