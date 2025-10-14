CADERNETA - Projeto PHP + MySQL (pronto para XAMPP)

Passos rápidos para rodar:
1) Copie a pasta 'caderneta' para o diretório do seu XAMPP:
   - Windows: C:\xampp\htdocs\
   - Linux (XAMPP): /opt/lampp/htdocs/

2) Inicie Apache e MySQL através do painel do XAMPP.

3) Abra o phpMyAdmin (http://localhost/phpmyadmin) e importe o arquivo:
   database/caderneta.sql
   - Isso criará o banco 'caderneta' e as tabelas. Um usuário de exemplo também é inserido:
     email: teste@local.test
     senha: senha123

4) Se seu MySQL usar senha para root, edite config/db.php e ajuste $DB_PASS.

5) Acesse no navegador:
   http://localhost/caderneta/

Observações:
- O projeto é simples e didático. Não é um produto para produção.
- Para segurança em produção: use prepared statements (já usados), HTTPS, validações adicionais e proteções CSRF.
