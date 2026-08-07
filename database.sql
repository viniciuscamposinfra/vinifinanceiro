CREATE TABLE usuarios (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(100) NOT NULL,

    usuario VARCHAR(50) NOT NULL UNIQUE,

    senha VARCHAR(255) NOT NULL,

    tipo ENUM('admin','usuario') DEFAULT 'usuario',

    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);