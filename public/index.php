<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/DbOperations.php';

$app = AppFactory::create();
$app->setBasePath('/sadiahcafe/sadiahcafe');
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);


$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

$app->post('/createuser', function (Request $request, Response $response) {
    if (!haveEmptyParameters(array('nophone', 'password', 'name', 'username', 'email'), $response)) {
        $request_data = $_REQUEST;

        $nophone = $request_data['nophone'];
        $password = $request_data['password'];
        $name = $request_data['name'];
        $username = $request_data['username'];
        $email = $request_data['email'];


        $hash_password = md5($password);;

        $db = new DbOperations;

        $result = $db->createUser($nophone, $hash_password, $name, $username, $email);

        if ($result == USER_CREATED) {
            $message = array();
            $message['error'] = false;
            $message['message'] = 'User Created Successfully.';

            $response->getBody()->write(json_encode($message));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(201);
        } elseif ($result == USER_FAILURE) {
            $message = array();
            $message['error'] = true;
            $message['message'] = 'Some error occurred.';

            $response->getBody()->write(json_encode($message));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        } elseif ($result == USER_EXISTS) {
            $message = array();
            $message['error'] = true;
            $message['message'] = 'User Already Exists.';

            $response->getBody()->write(json_encode($message));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        }
    }

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(422);
});

$app->post('/insertcart', function (Request $request, Response $response) {
    if (!haveEmptyParameters(array('title', 'quantity', 'u_id'), $response)) {
        $request_data = $_REQUEST;

        $menuitem = $request_data['title'];
        $quantity = $request_data['quantity'];
        $user_id = $request_data['u_id'];

        $db = new DbOperations;
        $menuname = $db->getMenuItemByName($menuitem);
        $menuitem = $menuname[0]['idmenu']; // Extract the correct value from the array


        $existingItem = null; // Initialize $existingItem as null

        // Check if the item with the same name already exists for the user
        // Check if the item with the same name already exists for the user
        $cartItems = $db->checkCartItems($menuname, $user_id);

        error_log('Debug - Cart Items:');
        error_log(print_r($cartItems, true)); // Log the cart items for debugging

        $existingItem = null;


        foreach ($cartItems as $item) {
            if ($item['idorder'] && $item['quantity']) {
                $existingItem = $item;
                break; // Exit the loop once a matching item is found
            }
        }
        $message = array();

        // Check if the item with the same name already exists for the user


        if ($existingItem) {
            // If the item already exists, update the quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            $result = $db->updatecart($newQuantity, $existingItem['idorder']);

            if ($result) {
                $message['error'] = false;
                $message['message'] = 'Item Quantity Updated Successfully.';
            } else {
                $message['error'] = true;
                $message['message'] = 'Failed to Update Item Quantity.';
            }
        } else {


            // If the item does not exist, insert a new item
            $result = $db->insertcart($user_id);
            if ($result) {
                $idorder = $db->getorder($user_id);
                if ($idorder != null) {
                    if ($menuname) {
                        $rest = $db->insertcartdetails($menuname, $quantity, $result);
                        if ($rest == ITEMS_ADDED) {
                            $message['error'] = false;
                            $message['message'] = 'Item Inserted Successfully.';
                            $message['id'] = $idorder;
                            $message['cart_details'] = $rest;
                            $message['menuitem'] = $menuname;
                            $message['$cartItems'] = $cartItems;
                        } else {
                            $message['error'] = true;
                            $message['message'] = 'Failed to Insert Item.';
                        }
                    }
                }
            } else {
                $message['error'] = true;
                $message['message'] = 'Failed to Insert Item.';
            }
        }

        $response->getBody()->write(json_encode($message));

        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus($message['error'] ? 422 : 401);
    }

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(422);
});

$app->post('/insertpayment', function (Request $request, Response $response) {
    if (!haveEmptyParameters(array('totalamount', 'user_id', 'totalquantity'), $response)) {
        $request_data = $_REQUEST;

        $totalamount = $request_data['totalamount'];
        $user_id = $request_data['user_id'];
        $totalquantity = $request_data['totalquantity'];

        $db = new DbOperations;

        $idorder = $db->getorder($user_id);
        $orderid = $idorder[0]['idorder'];
        $result = $db->insertpayment($totalamount, $user_id, $orderid);

        if ($result) {
            $updatecart = $db->updatecartsconfirmed($user_id);

            $message = array();
            $message['error'] = false;
            $message['message'] = 'Payment Successfully.';
            $message['updatecart'] = $updatecart;
            $message['idpayment'] = $result;
            

            $response->getBody()->write(json_encode($message));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(200);  // Use 200 for success
        } elseif ($result == ITEMS_NOT_ADDED) {
            $message = array();
            $message['error'] = true;
            $message['message'] = 'Some error occurred.';

            $response->getBody()->write(json_encode($message));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);  // Use 422 for unprocessable entity
        } elseif ($result == ITEMS_ALREADY_ADDED) {
            $message = array();
            $message['error'] = true;
            $message['message'] = 'Payment Already made.';

            $response->getBody()->write(json_encode($message));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);  // Use 422 for unprocessable entity
        }
    }

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(422);  // Use 422 for unprocessable entity
});




$app->post('/userlogin', function (Request $request, Response $response) {
    if (!haveEmptyParameters(array('username', 'password'), $response)) {
        $request_data = $_REQUEST;

        $username = $request_data['username'];
        $password = $request_data['password'];

        $db = new DbOperations();
        $result = $db->userlogin($username, $password);
        if ($result == USER_AUTHENTICATED) {
            $user = $db->getUserByusername($username);
            $response_data = array();
            $response_data['error'] = false;
            $response_data['message'] = 'Login Successful';
            $response_data['user'] = $user;

            $response->getBody()->write(json_encode($response_data));
            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(200);
        } elseif ($result == USER_NOT_FOUND) {

            $response_data = array();

            $response_data['error'] = true;
            $response_data['message'] = 'User NOt Exist';

            $response->getBody()->write(json_encode($response_data));
            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(404);
        } elseif ($result == USER_PASSWORD_DO_NOT_MATCH) {
            $response_data = array();

            $response_data['error'] = true;
            $response_data['message'] = 'Invalid Credentials';

            $response->getBody()->write(json_encode($response_data));
            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(404);
        }
    }
    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(422);
});

$app->get('/allcategory', function (Request $request, Response $response) {
    $db = new DbOperations();

    $category = $db->getcategory();

    $response_data['error'] = false;
    $response_data['category'] = $category;

    $response->getBody()->write(json_encode($response_data));
    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200);
});

$app->get('/getnotification', function (Request $request, Response $response) {

     if (!haveEmptyParameters(array('idpayment'), $response)) {
        $request_data = $_REQUEST;

        $idpayment = $request_data['idpayment'];
     
        $db = new DbOperations();

        $category = $db->getNotification($idpayment);

        $response_data['error'] = false;
        $response_data['message'] = "notification is get";
        $response_data['category'] = $category;

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
     } else {
        $response_data['error'] = false;
        $response_data['message'] = "idpayment is null";

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(422);
     } 
});

$app->get('/allusers', function (Request $request, Response $response) {
    $db = new DbOperations();

    $users = $db->getAllUsers();

    $response_data['error'] = false;
    $response_data['users'] = $users;

    $response->getBody()->write(json_encode($response_data));
    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200);
});

$app->get('/allcart', function (Request $request, Response $response) {

    $request_data = $_REQUEST;

    if (isset($request_data['u_id'])) {

        $u_id = $request_data['u_id']; // Retrieve the username from the route parameters

        $db = new DbOperations();

        $cartItems = $db->getAllCartItems($u_id);

        $response_data['cartItems'] = $cartItems;

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    } else {
        // Handle the case where "username" parameter is missing
        $error_response['error'] = true;
        $error_response['message'] = 'u_id parameter is missing in the request';
        $response->getBody()->write(json_encode($error_response));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(400); // You can use a different HTTP status code if appropriate
    }
});
$app->get('/allpaymentitems', function (Request $request, Response $response) {

    $request_data = $_REQUEST;

    if (isset($request_data['u_id'])) {

        $u_id = $request_data['u_id']; // Retrieve the username from the route parameters

        $db = new DbOperations();

        $cartItems = $db->getAllpaymentitems($u_id);

        $response_data['paymentitems'] = $cartItems;

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    } else {
        // Handle the case where "username" parameter is missing
        $error_response['error'] = true;
        $error_response['message'] = 'u_id parameter is missing in the request';
        $response->getBody()->write(json_encode($error_response));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(400); // You can use a different HTTP status code if appropriate
    }
});

$app->get('/allmenu', function (Request $request, Response $response, $args) {
    // Retrieve the username from the route parameters


    if (!haveEmptyParameters(array('category'), $response)) {
        $request_data = $_REQUEST;

        $categories = explode(',', $request_data['category']);

        $db = new DbOperations();

        $menuItems = $db->getAllmenuItems($categories);

        $response_data['menuItems'] = $menuItems;

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    } else {
        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'Please try again';


        $response->getBody()->write(json_encode($response_data));

        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(422);
    }


    // Handle the case where "username" parameter is missing
});


$app->put('/updateuser', function (Request $request, Response $response) {
    if (!haveEmptyParameters(array('nophone', 'name', 'username', 'id', 'email'), $response)) {
        $request_data = $_REQUEST;

        $nophone = $request_data['nophone'];
        $name = $request_data['name'];
        $username = $request_data['username'];
        $id = $request_data['id'];
        $email = $request_data['email'];

        $db = new DbOperations();

        if ($db->updateUser($nophone, $name, $username, $id, $email)) {
            // Retrieve the updated user data
            $updatedUser = $db->getUserByusername($username);

            $response_data = array();
            $response_data['error'] = false;
            $response_data['message'] = 'User Updated Successfully';
            $response_data['user'] = $updatedUser; // Include the user data in the response

            $response->getBody()->write(json_encode($response_data));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(200);
        } else {
            $response_data = array();
            $response_data['error'] = true;
            $response_data['message'] = 'Please try again';

            $response->getBody()->write(json_encode($response_data));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        }
    }

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200); // You may want to change the status code if there's an error
});


$app->put('/updatecartitem', function (Request $request, Response $response, array $args) {


    if (!haveEmptyParameters(array('quantity', 'o_id'), $response)) {
        $request_data = $_REQUEST;

        $quantity = $request_data['quantity'];
        $id = $request_data['o_id'];

        $db = new DbOperations();

        if ($db->updatecart($quantity, $id)) {
            // Retrieve the updated user data
            $updatedUser = $db->getCartItems($id);


            // Populate the 'user' field with the updated user data


            if ($updatedUser != null) {
                $response_data = array();
                $response_data['error'] = false;
                $response_data['message'] = 'Cart Items Updated Successfully';
                $response->getBody()->write(json_encode($response_data));

                return $response
                    ->withHeader('Content-type', 'application/json')
                    ->withStatus(200);
            } else {
                $response_data = array();
                $response_data['error'] = true;
                $response_data['message'] = 'user data is ';

                $response->getBody()->write(json_encode($response_data));

                return $response
                    ->withHeader('Content-type', 'application/json')
                    ->withStatus(422);
            }
        } else {
            $response_data = array();
            $response_data['error'] = true;
            $response_data['message'] = 'Please try again';

            $response->getBody()->write(json_encode($response_data));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        }
    }

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200);
});

$app->put('/updatepayments', function (Request $request, Response $response, array $args) {


    if (!haveEmptyParameters(array('u_id', 'paymentref', 'billcode', 'idpayment'), $response)) {
        $request_data = $_REQUEST;
        $id = $request_data['u_id'];
        $payrefnum = $request_data['paymentref'];
        $billcode = $request_data['billcode'];
        $idpayment = $request_data['idpayment'];

        $db = new DbOperations();

        if ($db->updateforpaymentorder($id)) {
        
            if ($db->updateforpaymenttable($id, $idpayment,$payrefnum, $billcode)) {
                $response_data = array();
                $response_data['error'] = false;
                $response_data['message'] = 'payments Updated Successfully';
                $response->getBody()->write(json_encode($response_data));

                return $response
                    ->withHeader('Content-type', 'application/json')
                    ->withStatus(200);
            } else {
                $response_data = array();
                $response_data['error'] = true;
                $response_data['message'] = 'update for payment table is not inserted';

                $response->getBody()->write(json_encode($response_data));

                return $response
                    ->withHeader('Content-type', 'application/json')
                    ->withStatus(422);
            }
        } else {
            $response_data = array();
            $response_data['error'] = true;
            $response_data['message'] = 'update for order table is not inserted';

            $response->getBody()->write(json_encode($response_data));

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        }
    }

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200);
});


$app->put('/updatepassword', function (Request $request, Response $response) {


    if (!haveEmptyParameters(array('currentpassword', 'newpassword', 'username'), $response)) {
        $request_data = $_REQUEST;

        $currentpassword = $request_data['currentpassword'];
        $newpassword = $request_data['newpassword'];
        $username = $request_data['username'];

        $db = new DbOperations();

        $result = $db->updatePassword($currentpassword, $newpassword, $username);

        if ($result == PASSWORD_CHANGED) {
            $response_data = array();

            $response_data['error'] = false;
            $response_data['message'] = 'Password Changed';

            $response->getBody()->write(json_encode($response_data));
            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(200);
        } elseif ($result == PASSWORD_DO_NOT_MATCH) {
            $response_data = array();

            $response_data['error'] = true;
            $response_data['message'] = 'You have given wrong password';

            $response->getBody()->write(json_encode($response_data));
            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        } elseif ($result == PASSWORD_NOT_CHANGED) {
            $response_data = array();

            $response_data['error'] = false;
            $response_data['message'] = 'some error ocurred';

            $response->getBody()->write(json_encode($response_data));
            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus(422);
        }
    }
    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(422);
});

$app->delete('/deleteuser/{u_id}', function (Request $request, Response $response, array $args) {
    $id = $args['u_id'];

    $db = new DbOperations();

    if ($db->deleteUser($id)) {
        $response_data['error'] = false;
        $response_data['message'] = 'User has been deleted';
    } else {
        $response_data['error'] = true;
        $response_data['message'] = 'Please Try Again later';
    }
    $response->getBody()->write(json_encode($response_data));
    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200);
});

$app->delete('/deleteitemcart', function (Request $request, Response $response, array $args) {

    if (!haveEmptyParameters(array('o_id'), $response)) {
        $request_data = $_REQUEST;

        $id = $request_data['o_id'];
        $db = new DbOperations();

        $response_data[] = array();

        if ($db->deleteitems($id)) {

            $response_data['error'] = false;
            $response_data['message'] = 'items has been deleted';
        } else {
            $response_data['error'] = true;
            $response_data['message'] = 'Please Try Again later';
        }
        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    }
});
function haveEmptyParameters($required_params, $response){

    $error = false;
    $error_params = '';
    $request_params = $_REQUEST;

    foreach ($required_params as $param) {
        if (!isset($request_params[$param]) || strlen($request_params[$param]) <= 0) {
            $error = true;
            $error_params .= $param . ', ';
        }
    }

    if ($error) {
        $error_detail = array();
        $error_detail['error'] = true;
        $error_detail['message'] = 'Required parameters ' . substr($error_params, 0, -2) . ' are either missing or empty';

        $response->getBody()->write(json_encode($error_detail));
    }

    return $error;
}

$app->run();