<?php
session_start();
require_once __DIR__ . '/config/db.php';
$errors = [];

// Redireciona se o usuário já estiver logado
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) $errors[] = 'Email inválido.';
    if (!$password) $errors[] = 'Senha obrigatória.';

    if (empty($errors)) {
        // 1. Buscar o usuário pelo email e buscar a coluna 'password_hash' (CORRIGIDO)
        $stmt = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. Verificar senha (usando 'password_hash')
        if ($user && password_verify($password, $user['password_hash'])) {
            // Sucesso no login
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = 'Email ou senha incorretos.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caderneta — Entrar</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  
  <style>
    /* PADRÃO DE CORES ROXO/LILÁS */
    :root {
      --purple: #6c5ce7; /* Um roxo mais vibrante */
      --light-purple: #9370db;
    }
    .text-purple { color: var(--purple) !important; }
    .btn-purple { 
      background-color: var(--purple); 
      border-color: var(--purple); 
      color: white; 
      transition: background-color 0.3s, border-color 0.3s;
    }
    .btn-purple:hover { 
      background-color: #5d50d6; 
      border-color: #5d50d6; 
      color: white; 
    }

    /* LAYOUT 'SPLIT SCREEN' MODERNO */
    body {
        /* Fundo suave para contraste */
        background-color: #f0f2f5; 
        display: flex;
        align-items: stretch; /* Estica o conteúdo verticalmente */
        justify-content: center;
        min-height: 100vh;
        margin: 0;
    }
    .auth-container {
        /* Ocupa a tela inteira em telas grandes, centralizado */
        display: flex;
        width: 100%;
        max-width: 1000px; /* Limite de largura para desktop */
        min-height: 700px; /* Altura mínima para o visual */
        background: white;
        border-radius: 1.5rem; /* Bordas mais suaves */
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1); /* Sombra mais destacada */
        overflow: hidden;
        margin: 2rem;
    }
    /* Seção de Marca (Fundo Roxo) */
    .brand-section {
        background: linear-gradient(135deg, var(--purple), #8a2be2); /* Gradiente moderno */
        color: white;
        padding: 4rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        /* Adiciona uma sutil animação ou estilo */
        position: relative;
    }
    .brand-section::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.05); /* Overlay sutil */
    }
    .brand-title {
        font-size: 3rem;
        font-weight: 700; /* Mais negrito */
        margin-bottom: 0.5rem;
        z-index: 1; /* Garante que fique acima do overlay */
    }
    .brand-section h2 {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        z-index: 1;
    }
    .brand-section p {
        font-size: 1rem;
        opacity: 0.85;
        z-index: 1;
    }

    /* Seção do Formulário (Fundo Branco) */
    .form-section {
        padding: 4rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .form-section h3 {
        font-weight: 700;
        color: #343a40; /* Cor escura para melhor legibilidade */
        margin-bottom: 2rem;
    }
    .form-control {
        border-radius: 0.5rem; /* Bordas arredondadas nos campos */
        padding: 0.75rem 1rem;
        border-color: #ced4da;
    }
    .form-control:focus {
        border-color: var(--purple);
        box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
    }
    .form-label {
        font-weight: 600; /* Rótulos mais destacados */
        color: #495057;
    }
    
    /* Adaptação para telas menores (responsividade) */
    @media (max-width: 992px) {
        .auth-container {
            flex-direction: column;
            width: 95%;
            margin: 1rem auto;
            min-height: auto;
        }
        .brand-section, .form-section {
            padding: 2.5rem;
            flex: none; /* Desativa o flex-grow */
        }
        .brand-section {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            text-align: left;
            align-items: flex-start;
        }
        .brand-title {
            font-size: 2.5rem;
        }
    }
    @media (max-width: 576px) {
        .brand-section, .form-section {
            padding: 1.5rem;
        }
    }
  </style>
</head>
<body>
  <div class="auth-container">
    
    <div class="brand-section">
      <div class="w-100">
        <h1 class="brand-title">Caderneta</h1>
        <h2 class="h4 mt-3">Controle total das suas finanças</h2>
        <p class="mt-2">Gerencie suas receitas e despesas com **simplicidade** e **segurança**.</p>
        <p class="mt-5 pt-4 small opacity-75">
          "A organização é a chave para a liberdade financeira."
        </p>
      </div>
    </div>

    <div class="form-section">
      <h3 class="mb-4">Acesse sua conta</h3>
      
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            <?php echo implode('<br>', $errors); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email" required placeholder="seu@exemplo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div class="mb-4">
          <label for="password" class="form-label">Senha</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        
        <button class="btn btn-purple btn-lg w-100 mb-3" type="submit">
          Entrar
        </button>
      </form>
      
      <p class="text-center small mt-4">
        Não tem uma conta? <a href="register.php" class="text-purple fw-bold text-decoration-none">Crie uma agora</a>
      </p>
      <p class="text-center small">
        <a href="#" class="text-muted text-decoration-none">Esqueceu sua senha?</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>