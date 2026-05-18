<?php
class Notification {
    private static $instance = null;
    private $notifications = [];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add($message, $type = 'info', $duration = 5000) {
        $this->notifications[] = [
            'message' => $message,
            'type' => $type,
            'duration' => $duration
        ];
    }

    public function getNotifications() {
        return $this->notifications;
    }

    public function clear() {
        $this->notifications = [];
    }
}

// Função helper para adicionar notificações
function notify($message, $type = 'info', $duration = 5000) {
    Notification::getInstance()->add($message, $type, $duration);
} 