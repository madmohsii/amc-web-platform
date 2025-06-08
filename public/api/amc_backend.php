<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function sendJSON($data) {
    echo json_encode($data);
    exit;
}

// Gestion simple avec fichiers JSON
class SimpleProjectManager {
    private $dataDir;
    private $usersFile;
    private $projectsFile;
    
    public function __construct() {
        $this->dataDir = dirname(dirname(__DIR__)) . '/data';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        $this->usersFile = $this->dataDir . '/users.json';
        $this->projectsFile = $this->dataDir . '/projects.json';
        
        // Créer les fichiers s'ils n'existent pas
        if (!file_exists($this->usersFile)) {
            file_put_contents($this->usersFile, json_encode([]));
        }
        if (!file_exists($this->projectsFile)) {
            file_put_contents($this->projectsFile, json_encode([]));
        }
    }
    
    private function getUsers() {
        $content = file_get_contents($this->usersFile);
        return json_decode($content, true) ?: [];
    }
    
    private function saveUsers($users) {
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    private function getProjects() {
        $content = file_get_contents($this->projectsFile);
        return json_decode($content, true) ?: [];
    }
    
    private function saveProjects($projects) {
        file_put_contents($this->projectsFile, json_encode($projects, JSON_PRETTY_PRINT));
    }
    
    public function register($username, $email, $password) {
        $users = $this->getUsers();
        
        foreach ($users as $user) {
            if ($user['username'] === $username || $user['email'] === $email) {
                throw new Exception('Utilisateur ou email déjà existant');
            }
        }
        
        $newUser = [
            'id' => count($users) + 1,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'teacher',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $users[] = $newUser;
        $this->saveUsers($users);
        
        return $newUser['id'];
    }
    
    public function login($username, $password) {
        $users = $this->getUsers();
        
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash']);
                return $user;
            }
        }
        
        return false;
    }
    
    public function createProject($userId, $name, $description = '') {
        $projects = $this->getProjects();
        
        $newProject = [
            'id' => count($projects) + 1,
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'exam_count' => 0
        ];
        
        $projects[] = $newProject;
        $this->saveProjects($projects);
        
        return $newProject['id'];
    }
    
    public function getUserProjects($userId) {
        $projects = $this->getProjects();
        $userProjects = [];
        
        foreach ($projects as $project) {
            if ($project['user_id'] == $userId) {
                $userProjects[] = $project;
            }
        }
        
        usort($userProjects, function($a, $b) {
            return strtotime($b['updated_at']) - strtotime($a['updated_at']);
        });
        
        return $userProjects;
    }
    
    public function getUserStats($userId) {
        $projects = $this->getUserProjects($userId);
        
        return [
            'total_projects' => count($projects),
            'total_exams' => array_sum(array_column($projects, 'exam_count')),
            'total_corrections' => 0
        ];
    }
}

function requireLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendJSON(['success' => false, 'error' => 'Connexion requise']);
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

$manager = new SimpleProjectManager();

try {
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'check_session':
            $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
            
            sendJSON([
                'success' => true,
                'logged_in' => $isLoggedIn,
                'user' => $isLoggedIn ? [
                    'id' => $_SESSION['user_id'] ?? 1,
                    'username' => $_SESSION['username'] ?? 'test',
                    'role' => $_SESSION['role'] ?? 'teacher'
                ] : null
            ]);
            break;
            
        case 'register':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($email) || empty($password)) {
                sendJSON(['success' => false, 'error' => 'Tous les champs sont requis']);
            }
            
            $userId = $manager->register($username, $email, $password);
            sendJSON(['success' => true, 'message' => 'Inscription réussie', 'user_id' => $userId]);
            break;
            
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                sendJSON(['success' => false, 'error' => 'Identifiants requis']);
            }
            
            $user = $manager->login($username, $password);
            
            if ($user) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                sendJSON([
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'user' => $user
                ]);
            } else {
                sendJSON(['success' => false, 'error' => 'Identifiants incorrects']);
            }
            break;
            
        case 'logout':
            session_destroy();
            sendJSON(['success' => true, 'message' => 'Déconnexion réussie']);
            break;
            
        case 'get_user_stats':
            requireLogin();
            $stats = $manager->getUserStats(getCurrentUserId());
            sendJSON(['success' => true, 'stats' => $stats]);
            break;
            
        case 'get_user_projects':
            requireLogin();
            $projects = $manager->getUserProjects(getCurrentUserId());
            sendJSON(['success' => true, 'projects' => $projects]);
            break;
            
        case 'create_project':
            requireLogin();
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                sendJSON(['success' => false, 'error' => 'Le nom du projet est requis']);
            }
            
            $projectId = $manager->createProject(getCurrentUserId(), $name, $description);
            
            sendJSON([
                'success' => true,
                'project_id' => $projectId,
                'message' => 'Projet créé avec succès'
            ]);
            break;
            
        default:
            sendJSON(['success' => false, 'error' => 'Action non reconnue: ' . $action]);
    }
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()]);
}
?>
