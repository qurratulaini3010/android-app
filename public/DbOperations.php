<?php

class DbOperations
{
    private $con;

    function __construct()
    {
        require_once dirname(__FILE__) . '/DbConnect.php';
        $db = new DbConnect;
        $this->con = $db->connect();
    }

    public function createUser($nophone, $password, $name, $username, $email)
    {
        try {
            if (!$this->isUsernameExist($username)) {
                $stmt = $this->con->prepare("INSERT INTO customer (phone,  password, fullname, username, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $nophone, $password, $name, $username, $email);

                if ($stmt->execute()) {
                    return USER_CREATED;
                } else {
                    return USER_FAILURE;
                }
            }

            return USER_EXISTS;
        } catch (mysqli_sql_exception $e) {
            // Log or print the exception message for debugging
            echo 'Error: ' . $e->getMessage();
            return USER_FAILURE;
        }
    }

    public function insertcart($u_id)
{
    $stmt = $this->con->prepare("INSERT INTO orders (customer, status, options) VALUES (?, 'In the Cart', 'Takeaway')");
    $stmt->bind_param("i", $u_id);

    if ($stmt->execute()) {
        // Retrieve the last inserted ID
        $idorder = $this->con->insert_id;

        return $idorder; // Return the ID
    } else {
        return ITEMS_NOT_ADDED;
    }
}

    public function insertcartdetails($title, $quantity, $o_id)
{
    $stmt = $this->con->prepare("INSERT INTO `order_detail` (`orders`, `quantity`, `menu_item`) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $o_id, $quantity, $title);
     if ($stmt->execute()) {
        return ITEMS_ADDED;
    } else {
        return ITEMS_NOT_ADDED;
    }
}

    public function insertpayment($totalamount, $user_id, $idorder)
{
    $status = "Not Done Payment";
    $option = "Take Away";

    $stmt = $this->con->prepare("INSERT INTO payment(totalamount, customer, status, orders) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $totalamount, $user_id, $status, $idorder);

    if ($stmt->execute()) {
        $idorder = $this->con->insert_id;
        return $idorder; 
    } else {
        $this->con->rollback();
        return ITEMS_NOT_ADDED;
    }
}



    public function userlogin($username, $password)
    {
        if ($this->isUsernameExist($username)) {
            if ($this->getUserstatus($username) == 1) {
                $hashed_password = $this->getUsersPasswordByusername($username);
                if (md5($password) == $hashed_password) {
                    return USER_AUTHENTICATED;
                } else {
                    return USER_PASSWORD_DO_NOT_MATCH;
                }
            } else {
                return USER_NOT_FOUND;
            }
        }
    }

    public function getpayment($idpayment){

        $totalamount = 0.00;
        $phone = "";
        $email = "";
        $fullname = "";
        $stmt = $this->con->prepare("SELECT customer.phone, customer.email, customer.fullname, payment.totalamount FROM payment 
         JOIN customer on payment.customer = customer.u_id
         WHERE payment.idpayment=?");
        $stmt->bind_param("i", $idpayment);
        $stmt->execute();
        $stmt->bind_result($phone, $fullname, $email, $totalamount);
        $users = array();
        while ($stmt->fetch()) {
            $user = array();
            $user['phone'] = $phone;
            $user['fullname'] = $fullname;
            $user['totalamount'] = $totalamount;
            $user['email'] = $email;
            array_push($users, $user);
        }
        return $users;
    }

public function getNotification($idPayment) {
    $status = ""; // Set an initial status or use an appropriate default

    // Limit the number of iterations to prevent infinite loop
    $maxIterations = 10;
    $iterationCount = 0;

    $query = "SELECT status FROM payment WHERE idpayment = ?";
    $stmt = $this->con->prepare($query);

    if ($stmt === false) {
        // Handle error, e.g., log it or throw an exception
        return "Error occurred while preparing the statement";
    }

    $stmt->bind_param("i", $idPayment);


    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();

    return $status;
}

   



    public function getUsersPasswordByusername($username)
    {
        $stmt = $this->con->prepare("SELECT password FROM customer WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->fetch();
        return $password;
    }
    public function getUserstatus($username)
    {
        $stmt = $this->con->prepare("SELECT status FROM customer WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($status);
        $stmt->fetch();
        return $status;
    }

    public function getMenuItemByName($name)
{
    $stmt = $this->con->prepare("SELECT idmenu FROM menu_item WHERE name_menu = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['idmenu'];
    } else {
        return null; // Return null or any other appropriate value when no matching record is found.
    }
}



    public function getAllUsers()
    {
        $stmt = $this->con->prepare("SELECT u_id, phone, fullname, username, email FROM customer");
        $stmt->execute();
        $stmt->bind_result($id, $phone, $fullname, $username, $email);
        $users = array();
        while ($stmt->fetch()) {
            $user = array();
            $user['u_id'] = $id;
            $user['phone'] = $phone;
            $user['fullname'] = $fullname;
            $user['username'] = $username;
            $user['email'] = $email;
            array_push($users, $user);
        }
        return $users;
    }

    public function getcategory()
    {

        $stmt = $this->con->prepare("SELECT title, open_hr, close_hr, open_days, img FROM category");
        $stmt->execute();
        $stmt->bind_result($title, $open_hr, $close_hr, $open_days, $img);
        $category = array();
        while ($stmt->fetch()) {
            $cat = array();
            $cat['title'] = $title;
            $cat['open_hr'] = $open_hr;
            $cat['close_hr'] = $close_hr;
            $cat['open_days'] = $open_days;
            $cat['img'] = $img;
            array_push($category, $cat);
        }
        return $category;
    }

    public function getAllCartItems($id)
{
    $stmt = $this->con->prepare("SELECT `orders`.idorder, order_detail.quantity, menu_item.name_menu, menu_item.price FROM `orders`
    INNER JOIN order_detail ON `orders`.idorder = order_detail.orders
    INNER JOIN menu_item ON menu_item.idmenu = order_detail.menu_item
    WHERE `orders`.customer = ? AND `orders`.status = 'In the Cart'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cartItems = array();
    while ($row = $result->fetch_assoc()) {
        $cartItem = array();
        $cartItem['idorder'] = $row['idorder']; // Use the correct column name
        $cartItem['quantity'] = $row['quantity']; // Use the correct column name
        $cartItem['name_menu'] = $row['name_menu']; // Use the correct column name
        $cartItem['price'] = $row['price']; // Use the correct column name
        array_push($cartItems, $cartItem);
    }

    return $cartItems;
}
public function getAllpaymentitems($id)
{
    $stmt = $this->con->prepare("SELECT `orders`.idorder, order_detail.quantity, menu_item.name_menu, menu_item.price FROM `orders`
    INNER JOIN order_detail ON `orders`.idorder = order_detail.orders
    INNER JOIN menu_item ON menu_item.idmenu = order_detail.menu_item
    WHERE `orders`.customer = ? AND `orders`.status = 'Done Payment'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cartItems = array();
    while ($row = $result->fetch_assoc()) {
        $cartItem = array();
        $cartItem['idorder'] = $row['idorder']; // Use the correct column name
        $cartItem['quantity'] = $row['quantity']; // Use the correct column name
        $cartItem['name_menu'] = $row['name_menu']; // Use the correct column name
        $cartItem['price'] = $row['price']; // Use the correct column name
        array_push($cartItems, $cartItem);
    }

    return $cartItems;
}

    public function getAllmenuItems($categories)
    {
        // Create a comma-separated string of placeholders based on the number of categories
        $placeholders = implode(',', array_fill(0, count($categories), '?'));

        // Build the SQL query using placeholders
        $sql = "SELECT idmenu, name_menu, price, img, category FROM menu_item WHERE category IN ($placeholders)";

        $stmt = $this->con->prepare($sql);

        if ($stmt) {
            // Bind the parameters
            $types = str_repeat('s', count($categories));
            $stmt->bind_param($types, ...$categories);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $menuItems = array();

                while ($row = $result->fetch_assoc()) {
                    $menuItem = array();
                    $menuItem['idmenu'] = $row['idmenu'];
                    $menuItem['name_menu'] = $row['name_menu'];
                    $menuItem['price'] = $row['price'];
                    $menuItem['img'] = $row['img'];
                    $menuItem['category'] = $row['category'];
                    array_push($menuItems, $menuItem);
                }

                return $menuItems;
            } else {
                // Handle the query execution error
            }
        } else {
            // Handle the prepared statement creation error
        }
    }



    public function getorder($u_id)
    {
        $stmt = $this->con->prepare("SELECT idorder FROM `orders` WHERE status = 'in the cart' AND customer = ?");
        $stmt->bind_param("i", $u_id);

        $stmt->execute();
        $result = $stmt->get_result();
        $menuItems = array();
        while ($row = $result->fetch_assoc()) {
            $menuItem = array();
            $menuItem['idorder'] = $row['idorder'];
            array_push($menuItems, $menuItem);
        }

        return $menuItems;
    }


    public function getCartItems($id)
    {
        $stmt = $this->con->prepare("SELECT orders.idorder, order_detail.quantity, menu_item.name_menu, menu_item.price FROM `orders`
        INNER JOIN order_detail ON orders.idorder = order_detail.orders
        INNER JOIN menu_item ON menu_item.idmenu = order_detail.menu_item
        WHERE orders.idorder = ? AND orders.status = 'Not Done Payment'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cartItems = array();
        while ($row = $result->fetch_assoc()) {
            $cartItem = array();
            $cartItem['idorder'] = $row['idorder'];
            $cartItem['name_menu'] = $row['name_menu']; // Corrected column name
            $cartItem['quantity'] = $row['quantity']; // Corrected column name
            array_push($cartItems, $cartItem);
        }

        return $cartItems;
    }

   public function checkCartItems($menuitem, $id)
{
    $stmt = $this->con->prepare("SELECT orders.idorder, order_detail.quantity FROM `orders`
    INNER JOIN order_detail ON orders.idorder = order_detail.orders
    INNER JOIN menu_item ON menu_item.idmenu = order_detail.menu_item
    WHERE orders.customer = ? AND orders.status = 'In the Cart' AND menu_item.idmenu = ?");
    $stmt->bind_param("is", $id, $menuitem);
    $stmt->execute();
    $result = $stmt->get_result();

    $cartItems = array();

    while ($row = $result->fetch_assoc()) {
        if (isset($row['idorder']) && isset($row['quantity'])) {
            $cartItem = $row['idorder']; // Corrected column name
            $quantity = $row['quantity']; // Corrected column name
            $cartItems[] = array('idorder' => $cartItem, 'quantity' => $quantity);
        }
    }

    return $cartItems;
}





    public function getUserByusername($username)
    {
        $stmt = $this->con->prepare("SELECT u_id, phone, fullname, username, email FROM customer WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($id, $nophone, $name, $username, $email);
        $stmt->fetch();
        $user = array();
        $user['u_id'] = $id;
        $user['phone'] = $nophone;
        $user['fullname'] = $name;
        $user['username'] = $username;
        $user['email'] = $email;
        return $user;
    }

    public function updateUser($nophone, $name, $username, $id, $email)
    {
        $stmt = $this->con->prepare("UPDATE customer SET phone = ?,  fullname = ?, username = ?, email = ? WHERE u_id = ?");
        $stmt->bind_param("ssssi", $nophone, $name, $username, $email, $id);
        if ($stmt->execute())
            echo $stmt->error;
        return true;
        return false;
    }

    public function updatecart($quantity, $id)
{
    $stmt = $this->con->prepare("UPDATE order_detail SET quantity = ? WHERE `orders` = ?");
    $stmt->bind_param("ii", $quantity, $id);
    if ($stmt->execute()) {
        return true;
    } else {
        echo $stmt->error; // Print the error message for debugging
        return false;
    }
}


    public function updatecartsconfirmed($id)
    {
        $stmt = $this->con->prepare("UPDATE `orders` SET status = 'Not Done Payment' WHERE customer = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute())
            echo $stmt->error;
            return true;
        return false;
    }

    public function updateforpaymentorder($id)
    {
        $stmt = $this->con->prepare("UPDATE `orders` SET status = 'Done Payment' WHERE customer = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute())
            echo $stmt->error;
            return true;
        return false;
    }
    public function updateforpaymenttable($id, $idpayments,$paymentref, $billcode)
    {
        $stmt = $this->con->prepare("UPDATE `payment` SET status = 'Done Payment', idtransaction = '$paymentref', bilcode = '$billcode' WHERE customer = '$id' AND idpayment = '$idpayments'");
        if ($stmt->execute())
            echo $stmt->error;
            return true;
        return false;
    }





    public function updatePassword($currentpassword, $newpassword, $username)
    {
        $hashed_password = $this->getUsersPasswordByusername($username);

        if (md5($currentpassword) == $hashed_password) {
            $hash_password = md5($newpassword);
            $stmt = $this->con->prepare("UPDATE customer SET password = ? WHERE username = ?");

            $stmt->bind_param("ss", $hash_password, $username);

            if ($stmt->execute())
                return PASSWORD_CHANGED;
            return PASSWORD_NOT_CHANGED;
        } else {
            return PASSWORD_DO_NOT_MATCH;
        }
    }

    public function deleteUser($id)
    {
        $stmt = $this->con->prepare("DELETE FROM customer WHERE u_id = ?");
        $stmt->bind_param("s", $id);

        if ($stmt->execute())
            return true;
        return false;
    }

    public function deleteitems($id)
{
    $stmt = $this->con->prepare("DELETE `orders`, order_detail
                                FROM `orders`
                                INNER JOIN order_detail ON order_detail.orders = `orders`.idorder
                                WHERE `orders`.idorder = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}


    private function isUsernameExist($username)
    {
        $stmt = $this->con->prepare("SELECT u_id FROM customer WHERE username = ? AND status = '1'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }
}