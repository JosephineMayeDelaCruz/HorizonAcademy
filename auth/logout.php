<?php
// Import authentication functions and immediately log out the user and clear their session.
require_once __DIR__ . '/../config/auth.php';
logout();
