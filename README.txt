Caderneta Financeira — PHP
Caderneta Financeira é uma aplicação web simples para controle de entradas (receitas) e saídas (despesas), desenvolvida em PHP + MySQL, com interface em Bootstrap 5.
O objetivo é permitir que qualquer usuário registre seus gastos e receitas de forma fácil e rápida, mantendo uma visão organizada de suas finanças pessoais.
Funcionalidades atuais
Criar conta (cadastro de usuário)


Login e logout com validação de sessão


Registro de entradas (receitas)


Registro de saídas (despesas)


Exclusão automática de dados ao deletar um usuário (ON DELETE CASCADE)


Dashboard simples (após login)


Interface moderna com Bootstrap 5 + Bootstrap Icons


Tecnologias utilizadas
PHP 8+


MySQL / MariaDB


PDO (conexão segura com prepared statements)


Bootstrap 5.3


HTML + CSS + JS


📂 Estrutura do Projeto
caderneta-financeira-php/
│
├── config/
│   └── db.php        # Arquivo de conexão com o banco
│
├── database/
│   └── caderneta.sql # Script para criar o banco de dados
│
├── index.php         # Tela de login
├── register.php      # Tela de criação de conta
├── dashboard.php     # Tela inicial após login
├── logout.php        # Finaliza a sessão
│
├── entradas/         # CRUD de entradas (separado)
├── saidas/           # CRUD de saídas (separado)
│
└── README.md

 Como instalar e rodar
1 Clone o repositório
git clone https://github.com/RaylaneRamos/caderneta-financeira-php.git

2 Coloque o projeto na pasta do servidor local
Exemplo no XAMPP:
C:\xampp\htdocs\caderneta

3 Crie o banco de dados
Abra o phpMyAdmin e execute o arquivo:
database/caderneta.sql

Ele irá criar:
Banco caderneta


Tabela usuarios


Tabela entradas


Tabela saidas


(Opcional) Um usuário de exemplo


4 Configure a conexão
Edite o arquivo:
config/db.php

E ajuste para o seu MySQL local:
$DB_HOST = '127.0.0.1';
$DB_NAME = 'caderneta';
$DB_USER = 'root';
$DB_PASS = ''; 

5 Execute o projeto
Abra no navegador:
http://localhost/caderneta
http://localhost/caderneta-financeira-php/index.php

Segurança aplicada
Senhas protegidas com password_hash()


Login usando password_verify()


Prepared statements (PDO) para evitar SQL Injection


Uso de session_start() para autenticação


📝 Próximas funcionalidades (futuro)
Exportar entradas/saídas em Excel


Botão de remover entrada/saída pelo usuário


Melhorias no dashboard


Página de perfil do usuário


(Essas são ideias planejadas — não estão no sistema ainda.)
