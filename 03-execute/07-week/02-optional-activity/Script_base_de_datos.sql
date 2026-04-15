-- UBICACIÓN
INSERT INTO continente (nombre) VALUES ('América');

INSERT INTO pais (nombre, id_continente) VALUES ('Colombia', 1);

INSERT INTO departamento (nombre, id_pais) VALUES ('Cundinamarca', 1);

INSERT INTO ciudad (nombre, id_departamento) VALUES ('Bogotá', 1);

INSERT INTO barrio (nombre, id_ciudad) VALUES ('Chapinero', 1);

-- SEGURIDAD
INSERT INTO persona (nombre, correo, id_barrio)
VALUES ('Juan Pérez', 'juan@gmail.com', 1);

INSERT INTO usuario (username, password, id_persona)
VALUES ('juan123', '123456', 1);

INSERT INTO rol (nombre) VALUES ('Admin');

INSERT INTO usuario_rol (id_usuario, id_rol)
VALUES (1, 1);

-- COMERCIAL
INSERT INTO categoria (nombre) VALUES ('Tecnología');

INSERT INTO producto (nombre, precio, stock, id_categoria)
VALUES ('Mouse Gamer', 50000, 10, 1);

INSERT INTO factura (fecha, total, id_usuario)
VALUES (NOW(), 100000, 1);

INSERT INTO detalle_factura (id_factura, id_producto, cantidad, precio_unitario, subtotal)
VALUES (1, 1, 2, 50000, 100000);