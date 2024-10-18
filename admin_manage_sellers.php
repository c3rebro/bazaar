<!-- admin_manage_sellers.php -->
<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("location: index.php");
    exit;
}

require_once 'config.php';

$conn = get_db_connection();
initialize_database($conn);

$error = '';
$success = '';

// Function to check if seller ID exists
function seller_id_exists($conn, $seller_id) {
    $sql = "SELECT id FROM sellers WHERE id='$seller_id'";
    $result = $conn->query($sql);
    return $result->num_rows > 0;
}

// Handle seller addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_seller'])) {
    $family_name = $conn->real_escape_string($_POST['family_name']);
    $given_name = $conn->real_escape_string($_POST['given_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $street = $conn->real_escape_string($_POST['street']);
    $house_number = $conn->real_escape_string($_POST['house_number']);
    $zip = $conn->real_escape_string($_POST['zip']);
    $city = $conn->real_escape_string($_POST['city']);
    $verified = isset($_POST['verified']) ? 1 : 0;

    if (empty($family_name) || empty($email)) {
        $error = "Erforderliche Felder fehlen.";
    } else {
        // Generate a random unique ID between 1 and 10000
        do {
            $seller_id = rand(1, 10000);
            $sql = "SELECT id FROM sellers WHERE id='$seller_id'";
            $result = $conn->query($sql);
        } while ($result->num_rows > 0);

        // Generate a secure hash using the seller's email and ID
        $hash = hash('sha256', $email . $seller_id . $SECRET);

        $sql = "INSERT INTO sellers (id, email, reserved, family_name, given_name, phone, street, house_number, zip, city, hash, verified) VALUES ('$seller_id', '$email', 0, '$family_name', '$given_name', '$phone', '$street', '$house_number', '$zip', '$city', '$hash', '$verified')";
        if ($conn->query($sql) === TRUE) {
            $success = "Verkäufer erfolgreich hinzugefügt.";
            debug_log("Seller added: ID=$seller_id, Name=$family_name, Email=$email, Verified=$verified");
        } else {
            $error = "Fehler beim Hinzufügen des Verkäufers: " . $conn->error;
            debug_log("Error adding seller: " . $conn->error);
        }
    }
}

// Handle seller update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_seller'])) {
    $seller_id = $conn->real_escape_string($_POST['seller_id']);
    $family_name = $conn->real_escape_string($_POST['family_name']);
    $given_name = $conn->real_escape_string($_POST['given_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $street = $conn->real_escape_string($_POST['street']);
    $house_number = $conn->real_escape_string($_POST['house_number']);
    $zip = $conn->real_escape_string($_POST['zip']);
    $city = $conn->real_escape_string($_POST['city']);
    $verified = isset($_POST['verified']) ? 1 : 0;

    if (empty($family_name) || empty($email)) {
        $error = "Erforderliche Felder fehlen.";
    } else {
        $sql = "UPDATE sellers SET family_name='$family_name', given_name='$given_name', email='$email', phone='$phone', street='$street', house_number='$house_number', zip='$zip', city='$city', verified='$verified' WHERE id='$seller_id'";
        if ($conn->query($sql) === TRUE) {
            $success = "Verkäufer erfolgreich aktualisiert.";
            debug_log("Seller updated: ID=$seller_id, Name=$family_name, Email=$email, Verified=$verified");
        } else {
            $error = "Fehler beim Aktualisieren des Verkäufers: " . $conn->error;
            debug_log("Error updating seller: " . $conn->error);
        }
    }
}

// Handle seller deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_seller'])) {
    $seller_id = $conn->real_escape_string($_POST['seller_id']);
    $sql = "DELETE FROM sellers WHERE id='$seller_id'";
    if ($conn->query($sql) === TRUE) {
        $success = "Verkäufer erfolgreich gelöscht.";
        debug_log("Seller deleted: ID=$seller_id");
    } else {
        $error = "Fehler beim Löschen des Verkäufers: " . $conn->error;
        debug_log("Error deleting seller: " . $conn->error);
    }
}

// Handle product deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
    $product_id = $conn->real_escape_string($_POST['product_id']);
    $seller_id = $conn->real_escape_string($_POST['seller_id']);

    $sql = "DELETE FROM products WHERE id='$product_id' AND seller_id='$seller_id'";
    if ($conn->query($sql) === TRUE) {
        $success = "Produkt erfolgreich gelöscht.";
        debug_log("Product deleted: ID=$product_id, Seller ID=$seller_id");
    } else {
        $error = "Fehler beim Löschen des Produkts: " . $conn->error;
        debug_log("Error deleting product: " . $conn->error);
    }
}

// Handle product update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
    $product_id = $conn->real_escape_string($_POST['product_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $price = $conn->real_escape_string($_POST['price']);

    $sql = "UPDATE products SET name='$name', price='$price' WHERE id='$product_id'";
    if ($conn->query($sql) === TRUE) {
        $success = "Produkt erfolgreich aktualisiert.";
        debug_log("Product updated: ID=$product_id, Name=$name, Price=$price");
    } else {
        $error = "Fehler beim Aktualisieren des Produkts: " . $conn->error;
        debug_log("Error updating product: " . $conn->error);
    }
}

// Fetch all sellers
$sql = "SELECT * FROM sellers";
$sellers_result = $conn->query($sql);
debug_log("Fetched sellers: " . $sellers_result->num_rows);

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verkäufer Verwalten</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-row {
            margin-bottom: 1rem;
        }
        .table-responsive {
            margin-top: 1rem;
        }
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mt-5">Verkäufer Verwalten</h2>
        <?php if ($error) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
        <?php if ($success) { echo "<div class='alert alert-success'>$success</div>"; } ?>

        <h3 class="mt-5">Neuen Verkäufer hinzufügen</h3>
        <form action="admin_manage_sellers.php" method="post">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="family_name">Nachname:</label>
                    <input type="text" class="form-control" id="family_name" name="family_name" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="given_name">Vorname:</label>
                    <input type="text" class="form-control" id="given_name" name="given_name">
                </div>
                <div class="form-group col-md-3">
                    <label for="email">E-Mail:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="phone">Telefon:</label>
                    <input type="text" class="form-control" id="phone" name="phone">
                </div>
                <div class="form-group col-md-3">
                    <label for="street">Straße:</label>
                    <input type="text" class="form-control" id="street" name="street">
                </div>
                <div class="form-group col-md-3">
                    <label for="house_number">Hausnummer:</label>
                    <input type="text" class="form-control" id="house_number" name="house_number">
                </div>
                <div class="form-group col-md-3">
                    <label for="zip">PLZ:</label>
                    <input type="text" class="form-control" id="zip" name="zip">
                </div>
                <div class="form-group col-md-3">
                    <label for="city">Stadt:</label>
                    <input type="text" class="form-control" id="city" name="city">
                </div>
                <div class="form-group col-md-3">
                    <label for="verified">Verifiziert:</label>
                    <input type="checkbox" class="form-control" id="verified" name="verified">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" name="add_seller">Verkäufer hinzufügen</button>
        </form>

        <h3 class="mt-5">Verkäuferliste</h3>
        <div class="table-responsive">
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nachname</th>
                        <th>Vorname</th>
                        <th>E-Mail</th>
                        <th>Verifiziert</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($sellers_result->num_rows > 0) {
                        while ($row = $sellers_result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$row['id']}</td>
                                    <td>{$row['family_name']}</td>
                                    <td>{$row['given_name']}</td>
                                    <td>{$row['email']}</td>
                                    <td>" . ($row['verified'] ? 'Ja' : 'Nein') . "</td>
                                    <td>
                                        <button class='btn btn-warning btn-sm' onclick='editSeller({$row['id']}, \"{$row['family_name']}\", \"{$row['given_name']}\", \"{$row['email']}\", \"{$row['phone']}\", \"{$row['street']}\", \"{$row['house_number']}\", \"{$row['zip']}\", \"{$row['city']}\", " . ($row['verified'] ? 'true' : 'false') . ")'>Bearbeiten</button>
                                        <form action='admin_manage_sellers.php' method='post' style='display:inline-block'>
                                            <input type='hidden' name='seller_id' value='{$row['id']}'>
                                            <button type='submit' name='delete_seller' class='btn btn-danger btn-sm'>Löschen</button>
                                        </form>
                                        <button class='btn btn-info btn-sm' onclick='toggleProducts({$row['id']})'>Produkte anzeigen</button>
										<a href='seller_products.php?seller_id={$row['id']}&hash={$row['hash']}' class='btn btn-success btn-sm'>Produkte erstellen</a>
                                        <a href='checkout.php?seller_id={$row['id']}' class='btn btn-success btn-sm'>Checkout</a>
                                    </td>
                                  </tr>";
                            echo "<tr id='seller-products-{$row['id']}' style='display:none;'>
                                    <td colspan='11'>
                                        <div class='table-responsive'>
                                            <table class='table table-bordered'>
                                                <thead>
                                                    <tr>
                                                        <th>Produktname</th>
                                                        <th>Preis</th>
                                                        <th>Aktionen</th>
                                                    </tr>
                                                </thead>
                                                <tbody id='products-{$row['id']}'>
                                                    <!-- Products will be loaded here via AJAX -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11'>Keine Verkäufer gefunden.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <a href="dashboard.php" class="btn btn-primary btn-block mt-3">Zurück zum Dashboard</a>
    </div>

    <!-- Edit Seller Modal -->
    <div class="modal fade" id="editSellerModal" tabindex="-1" role="dialog" aria-labelledby="editSellerModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="admin_manage_sellers.php" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSellerModalLabel">Verkäufer bearbeiten</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="seller_id" id="editSellerId">
                        <div class="form-group">
                            <label for="editSellerIdDisplay">Verkäufer-ID:</label>
                            <input type="text" class="form-control" id="editSellerIdDisplay" name="seller_id_display" disabled>
                        </div>
                        <div class="form-group">
                            <label for="editSellerFamilyName">Nachname:</label>
                            <input type="text" class="form-control" id="editSellerFamilyName" name="family_name" required>
                        </div>
                        <div class="form-group">
                            <label for="editSellerGivenName">Vorname:</label>
                            <input type="text" class="form-control" id="editSellerGivenName" name="given_name">
                        </div>
                        <div class="form-group">
                            <label for="editSellerEmail">E-Mail:</label>
                            <input type="email" class="form-control" id="editSellerEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="editSellerPhone">Telefon:</label>
                            <input type="text" class="form-control" id="editSellerPhone" name="phone">
                        </div>
                        <div class="form-group">
                            <label for="editSellerStreet">Straße:</label>
                            <input type="text" class="form-control" id="editSellerStreet" name="street">
                        </div>
                        <div class="form-group">
                            <label for="editSellerHouseNumber">Hausnummer:</label>
                            <input type="text" class="form-control" id="editSellerHouseNumber" name="house_number">
                        </div>
                        <div class="form-group">
                            <label for="editSellerZip">PLZ:</label>
                            <input type="text" class="form-control" id="editSellerZip" name="zip">
                        </div>
                        <div class="form-group">
                            <label for="editSellerCity">Stadt:</label>
                            <input type="text" class="form-control" id="editSellerCity" name="city">
                        </div>
                        <div class="form-group">
                            <label for="editSellerVerified">Verifiziert:</label>
                            <input type="checkbox" class="form-control" id="editSellerVerified" name="verified">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                        <button type="submit" class="btn btn-primary" name="edit_seller">Änderungen speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="admin_manage_sellers.php" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProductModalLabel">Produkt bearbeiten</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="editProductId">
                        <div class="form-group">
                            <label for="editProductName">Produktname:</label>
                            <input type="text" class="form-control" id="editProductName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="editProductPrice">Preis:</label>
                            <input type="number" class="form-control" id="editProductPrice" name="price" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                        <button type="submit" class="btn btn-primary" name="update_product">Änderungen speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function editSeller(id, family_name, given_name, email, phone, street, house_number, zip, city, verified) {
            $('#editSellerId').val(id);
            $('#editSellerIdDisplay').val(id);
            $('#editSellerFamilyName').val(family_name);
            $('#editSellerGivenName').val(given_name);
            $('#editSellerEmail').val(email);
            $('#editSellerPhone').val(phone);
            $('#editSellerStreet').val(street);
            $('#editSellerHouseNumber').val(house_number);
            $('#editSellerZip').val(zip);
            $('#editSellerCity').val(city);
            $('#editSellerVerified').prop('checked', verified);
            $('#editSellerModal').modal('show');
        }

        function toggleProducts(sellerId) {
            const row = $(`#seller-products-${sellerId}`);
            if (row.is(':visible')) {
                row.hide();
            } else {
                loadProducts(sellerId);
                row.show();
            }
        }

        function loadProducts(sellerId) {
            $.ajax({
                url: 'load_seller_products.php',
                method: 'GET',
                data: { seller_id: sellerId },
                success: function(response) {
                    $(`#products-${sellerId}`).html(response);
                },
                error: function() {
                    alert('Fehler beim Laden der Produkte.');
                }
            });
        }

        function editProduct(productId, name, price) {
            $('#editProductId').val(productId);
            $('#editProductName').val(name);
            $('#editProductPrice').val(price.toFixed(2));
            $('#editProductModal').modal('show');
        }
    </script>
</body>
</html>