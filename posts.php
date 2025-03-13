<?php

include_once(ROOT_PATH . "/app/database/db.php");
include_once(ROOT_PATH . "/app/helpers/middleware.php");

$table = 'posts';

$clientTopics = new \Laminas\Http\Client('http://localhost:8080/topics', ['timeout' => 30]);
$clientTopics->setMethod(\Laminas\Http\Request::METHOD_GET);
$responseTopics = $clientTopics->send();
if ($responseTopics->isSuccess()) {
    $topics = json_decode($responseTopics->getBody(), true);
} else {
    $topics = [];
}

$client = new \Laminas\Http\Client('http://localhost:8080/posts', [
    'timeout' => 30,
]);
$client->setMethod(\Laminas\Http\Request::METHOD_GET);
$response = $client->send();

if ($response->isSuccess()) {
    $posts = json_decode($response->getBody(), true);
} else {
    $posts = [];
    // Opcjonalnie: ustaw komunikat błędu lub loguj błąd
}

$errors = array();
$id = "";
$title = "";
$body = "";
$topic_id = "";
$published = "";

if (isset($_GET['id'])) {
    $postId = $_GET['id'];
    $client = new \Laminas\Http\Client('http://localhost:8080/posts/' . $postId, [
         'timeout' => 30,
    ]);
    $client->setMethod(\Laminas\Http\Request::METHOD_GET);
    $response = $client->send();
    
    if ($response->isSuccess()) {
        $post = json_decode($response->getBody(), true);
        
        $id = $post['id'];
        $title = $post['title'];
        $body = $post['body'];
        $ingredients = $post['ingredients'];
        $topic_id = $post['topic_id'];
        $published = $post['published'];
    } else {
        // Obsłuż sytuację, gdy nie udało się pobrać posta – np. ustaw komunikat błędu
        $_SESSION['message'] = 'Nie udało się pobrać posta';
        $id = $title = $body = $ingredients = $topic_id = $published = '';
    }
}

if(isset($_GET['delete'])){
    $postId = $_GET['delete'];
    $client = new \Laminas\Http\Client('http://localhost:8080/posts/' . $postId, [
         'timeout' => 30,
    ]);
    $client->setMethod(\Laminas\Http\Request::METHOD_DELETE);
    $response = $client->send();

    if($response->isSuccess()){
         $_SESSION['message'] = 'Post został usunięty';
    } else {
         $_SESSION['message'] = 'Błąd przy usuwaniu posta';
    }
}

if (isset($_GET['published']) && isset($_GET['p_id'])) {
    adminOnly();
    $published = $_GET['published'];
    $p_id = $_GET['p_id'];

    // Przygotowanie klienta HTTP i żądania PATCH
    $client = new \Laminas\Http\Client('http://localhost:8080/posts/' . $p_id, [
         'timeout' => 30,
    ]);
    $client->setMethod('PATCH'); // Używamy metody PATCH
    $patchData = ['published' => $published];
    $client->setRawBody(json_encode($patchData));
    $client->setEncType('application/json');

    $response = $client->send();

    if ($response->isSuccess()) {
         $_SESSION['message'] = "Zmieniono status widoczności posta";
         $_SESSION['type'] = "success";
         header("location: " . BASE_URL . "/admin/posts/index.php");
         exit();
    } else {
         $_SESSION['message'] = "Błąd przy zmianie statusu: " . $response->getStatusCode();
    }
}


if(isset($_POST['add-post'])){
    // Przygotowanie danych do wysłania do API
    $postData = [
         'user_id'     => $_POST['user_id'],
         'topic_id'    => $_POST['topic_id'],
         'title'       => $_POST['title'],
         'image'       => $_POST['image'],
         'image_p'     => $_POST['image_p'],
         'body'        => $_POST['body'],
         'ingredients' => $_POST['ingredients'],
    ];

    // Użycie Laminas HTTP Client do wysłania żądania POST do REST API
    $client = new \Laminas\Http\Client('http://localhost:8080/posts', [
         'timeout' => 30,
    ]);
    $client->setMethod(\Laminas\Http\Request::METHOD_POST);
    $client->setRawBody(json_encode($postData));
    $client->setEncType('application/json');

    $response = $client->send();

    if($response->isSuccess()){
         // Na przykład zapisz komunikat o sukcesie w sesji
         $_SESSION['message'] = 'Post został utworzony';
    } else {
         // Opcjonalnie: zapisz komunikat o błędzie
         $_SESSION['message'] = 'Błąd: ' . $response->getStatusCode();
    }
}

if(isset($_POST['update-post'])) {
    adminOnly();
    
    // Nie wywołujemy już starej walidacji, jeśli wszystko jest obsługiwane przez API Tools
    $errors = array();
    
    // Przetwarzanie przesyłania plików (jak wyżej) pozostaje bez zmian...
    if(!empty($_FILES['image']['name'])) {
        $image_name = time() . '_' . $_FILES['image']['name'];
        $destination = ROOT_PATH . "/assets/images/" . $image_name;
        $result = move_uploaded_file($_FILES['image']['tmp_name'], $destination);
        if ($result) {
            $_POST['image'] = $image_name;
        } else {
            array_push($errors, "Błąd z przesłaniem zdjęcia");
        }
    } else {
        array_push($errors, "Wymagane zdjęcie");
    }
    
    if(!empty($_FILES['image_p']['name'])) {
        $image_name = time() . '_' . $_FILES['image_p']['name'];
        $destination = ROOT_PATH . "/assets/images/" . $image_name;
        $result = move_uploaded_file($_FILES['image_p']['tmp_name'], $destination);
        if ($result) {
            $_POST['image_p'] = $image_name;
        } else {
            array_push($errors, "Błąd z przesłaniem zdjęcia");
        }
    } else {
        array_push($errors, "Wymagane zdjęcie");
    }

    if(count($errors) == 0) {
        $id = $_POST['id'];
        unset($_POST['update-post'], $_POST['id']);
        $_POST['user_id'] = $_SESSION['id'];
        $_POST['published'] = isset($_POST['published']) ? 1 : 0;
        $_POST['body'] = htmlentities($_POST['body']);

        // Użycie Laminas HTTP Client do wysłania żądania PUT do REST API
        $client = new \Laminas\Http\Client('http://localhost:8080/posts/' . $id, [
             'timeout' => 30,
        ]);
        $client->setMethod(\Laminas\Http\Request::METHOD_PUT);
        $client->setRawBody(json_encode($_POST));
        $client->setEncType('application/json');

        $response = $client->send();

        if($response->isSuccess()){
            $_SESSION['message'] = "Post został zmodyfikowany";
            $_SESSION['type'] = "success";
            header("location: " . BASE_URL . "/admin/posts/index.php");
            exit();
        } else {
            $_SESSION['message'] = "Błąd przy modyfikacji posta: " . $response->getStatusCode();
        }
    } else {
        // Jeśli wystąpiły błędy (np. przy przesyłaniu zdjęć), ustaw zmienne do ponownego wyświetlenia formularza
        $title = $_POST['title'];
        $body = $_POST['body'];
        $topic_id = $_POST['topic_id'];
        $published = isset($_POST['published']) ? 1 : 0;
    }
}
