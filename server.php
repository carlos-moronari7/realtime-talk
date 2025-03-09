// server.php
<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require __DIR__ . '/vendor/autoload.php';

class Chat implements MessageComponentInterface {
    private $clients;
    private $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->db = new PDO("mysql:host=localhost;dbname=chat_db", "root", "");
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nova conexão! ({$conn->resourceId})\n";
        
        // Enviar mensagens existentes do banco
        $stmt = $this->db->query("SELECT * FROM messages ORDER BY created_at ASC");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $conn->send(json_encode(['type' => 'history', 'data' => $messages]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if ($data['type'] === 'message') {
            // Salvar no banco
            $stmt = $this->db->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$data['user_id'], $data['message']]);
            
            // Enviar para todos os clientes
            foreach ($this->clients as $client) {
                $client->send(json_encode([
                    'type' => 'message',
                    'user_id' => $data['user_id'],
                    'message' => $data['message'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Conexão fechada! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Erro: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new Chat()
        )
    ),
    8080
);

$server->run();