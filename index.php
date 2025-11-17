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
        try {
            $stmt = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = 'Email ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Erro de conexão. Tente novamente.';
            // error_log("Login PDO Error: " . $e->getMessage()); // Para depuração
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
      --purple: #6c5ce7; /* Roxo vibrante */
      --dark-purple: #5b4dd1; /* Um roxo um pouco mais escuro para o gradiente */
      --light-purple: #9370db; /* Lilás */
    }
    .text-purple { color: var(--purple) !important; }
    .btn-purple { 
      background-color: var(--purple); 
      border-color: var(--purple); 
      color: white; 
      transition: background-color 0.3s, border-color 0.3s;
    }
    .btn-purple:hover { 
      background-color: var(--dark-purple); /* Mais escuro no hover */
      border-color: var(--dark-purple); 
      color: white; 
    }

    /* LAYOUT 'SPLIT SCREEN' MELHORADO */
    body {
      background-color: #f0f2f5; /* Fundo suave */
      display: flex;
      align-items: center; /* Centraliza verticalmente o container */
      justify-content: center; /* Centraliza horizontalmente o container */
      min-height: 100vh;
      margin: 0;
      padding: 1rem; /* Padding para evitar que o container toque as bordas em telas pequenas */
    }
    .auth-container {
      display: flex;
      width: 100%;
      max-width: 1000px; /* Limite de largura para desktop */
      min-height: 600px; /* Altura mínima ajustada para menos */
      background: white;
      border-radius: 1.25rem; /* Bordas mais suaves e consistentes */
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); /* Sombra mais sutil e elegante */
      overflow: hidden; /* Garante que o conteúdo respeite o border-radius */
    }
    
    /* Seção de Marca (Fundo Roxo com gradiente melhorado) */
    .brand-section {
      background: linear-gradient(135deg, var(--purple) 0%, var(--dark-purple) 100%); /* Gradiente mais suave */
      color: white;
      padding: 3.5rem; /* Padding ajustado */
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      position: relative;
    }
    .brand-section::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.08); /* Overlay sutil com mais opacidade */
      border-radius: inherit; /* Garante que o overlay tenha o mesmo border-radius do container */
    }
    .brand-title {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 0.75rem; /* Espaçamento ajustado */
      z-index: 1;
    }
    .brand-section h2 {
      font-size: 1.6rem; /* Um pouco maior */
      margin-bottom: 1.75rem; /* Espaçamento ajustado */
      z-index: 1;
      line-height: 1.3; /* Melhor legibilidade */
    }
    .brand-section p {
      font-size: 1.05rem; /* Um pouco maior */
      opacity: 0.9; /* Mais visível */
      z-index: 1;
    }

    /* Seção do Formulário (Fundo Branco) */
    .form-section {
      padding: 3.5rem; /* Padding ajustado */
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .form-section h3 {
      font-weight: 700;
      color: #343a40;
      margin-bottom: 2.25rem; /* Espaçamento ajustado */
      font-size: 1.8rem; /* Um pouco maior */
    }
    .form-control {
      border-radius: 0.6rem; /* Bordas mais suaves nos campos */
      padding: 0.85rem 1.1rem; /* Mais padding interno */
      border: 1px solid #dee2e6; /* Borda mais definida */
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
      border-color: var(--purple);
      box-shadow: 0 0 0 0.3rem rgba(108, 92, 231, 0.2); /* Sombra de foco mais suave */
    }
    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 0.4rem; /* Pequeno espaçamento abaixo do label */
    }
    .btn-lg {
        padding: 0.8rem 1.5rem; /* Padding do botão maior */
        font-size: 1.1rem; /* Fonte maior no botão */
        border-radius: 0.6rem; /* Bordas do botão mais suaves */
    }
    
    /* Adaptação para telas menores (responsividade aprimorada) */
    @media (max-width: 992px) {
      body {
        padding: 0; /* Remove o padding do body em mobile para maximizar espaço */
      }
      .auth-container {
        flex-direction: column;
        width: 100%; /* Ocupa a largura total */
        margin: 0; /* Remove margem */
        min-height: auto; /* Altura flexível */
        border-radius: 0; /* Sem borda arredondada em mobile */
        box-shadow: none; /* Sem sombra em mobile */
      }
      .brand-section {
        padding: 2.5rem 1.5rem; /* Padding ajustado para mobile */
        border-radius: 0; /* Garante que não haja borda arredondada herdada */
        text-align: left;
        align-items: flex-start;
      }
      .brand-section::after {
          border-radius: 0; /* Overlay também sem border-radius */
      }
      .brand-title {
        font-size: 2.2rem; /* Tamanho da fonte ajustado */
      }
      .brand-section h2 {
        font-size: 1.3rem; /* Tamanho da fonte ajustado */
      }
      .brand-section p {
        font-size: 0.9rem;
      }
      .form-section {
        padding: 2rem 1.5rem; /* Padding ajustado para mobile */
      }
      .form-section h3 {
        font-size: 1.5rem;
        margin-bottom: 1.8rem;
      }
    }
    @media (max-width: 576px) {
      .brand-section, .form-section {
        padding: 1.5rem; /* Padding menor em telas muito pequenas */
      }
      .brand-title {
        font-size: 2rem;
      }
      .brand-section h2 {
        font-size: 1.1rem;
      }
      .brand-section p {
        font-size: 0.85rem;
      }
      .form-section h3 {
        font-size: 1.3rem;
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