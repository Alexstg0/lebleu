-- Reservas cargadas de los calendarios mayo–agosto 2026 (Sra. Gardenia = García).
-- El script de setup solo lo corre si la tabla reservas está vacía.

INSERT INTO reservas (fecha, hora, cliente, socio_id, num_personas, duracion_horas, notas) VALUES
-- Reservas específicas
('2026-05-10','13:00','Familia Acosta',1,NULL,NULL,NULL),
('2026-05-12','15:30','Familia Acosta',1,NULL,NULL,NULL),
('2026-05-15','11:30','Sra Gardenia',2,NULL,NULL,NULL),
('2026-05-21','15:30','Familia García',2,NULL,NULL,NULL),
('2026-05-24','11:00','Familia García',2,NULL,NULL,NULL),
('2026-06-12','14:30','Sra Gardenia',2,11,NULL,NULL),
('2026-06-14','15:30','Sra Gardenia',2,NULL,NULL,NULL),
('2026-06-22','13:00','Familia Acosta',1,NULL,NULL,NULL),
('2026-06-26','15:30','Sra Gardenia',2,NULL,NULL,NULL),
('2026-06-27','13:30','Familia Acosta',1,NULL,NULL,NULL),
('2026-06-28','15:30','Familia García',2,6,NULL,NULL),
('2026-07-05','15:30','Familia García',2,NULL,NULL,NULL),
('2026-08-08','16:30','Maria del Mar Rigalti',NULL,6,NULL,'Renta · 4:30–7:30 PM · sábado de Familia Acosta'),
-- Sábados reservados para cada familia (alternados)
('2026-05-02',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-05-09',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-05-16',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-05-23',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-05-30',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-06-06',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-06-13',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-06-20',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-07-04',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-07-11',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-07-18',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-07-25',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-08-01',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-08-15',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia'),
('2026-08-22',NULL,'Familia Acosta',1,NULL,NULL,'Sábado reservado para la familia'),
('2026-08-29',NULL,'Familia García',2,NULL,NULL,'Sábado reservado para la familia');
