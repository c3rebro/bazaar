<?php
// Start session with secure settings
session_start([
    'cookie_secure' => true,   // Ensure the session cookie is only sent over HTTPS
    'cookie_httponly' => true, // Prevent JavaScript access to the session cookie
    'cookie_samesite' => 'Strict' // Add SameSite attribute for additional CSRF protection
]);

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce'; img-src 'self' 'nonce-$nonce' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';");

require_once 'utilities.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'cashier' && $_SESSION['role'] !== 'seller')) {
    header("location: login.php");
    exit;
}

$message = '';
$message_type = 'danger'; // Default message type for errors

$seller_id = $_SESSION['seller_id'];
$hash = $_SESSION['seller_hash'];

if (!isset($seller_id) || !isset($hash)) {
    echo "Loginfehler.";
    exit();
}

$conn = get_db_connection();
$sql = "SELECT * FROM sellers WHERE id=? AND hash=? AND verified=1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $seller_id, $hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '
        <!DOCTYPE html>
        <html lang="de">
        <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Hinweis zur Zertifikatssicherheit</title>
                <!-- Preload and link CSS files -->
                <link rel="preload" href="css/bootstrap.min.css" as="style" id="bootstrap-css">
                <link rel="preload" href="css/all.min.css" as="style" id="all-css">
                <link rel="preload" href="css/style.css" as="style" id="style-css">
                <noscript>
                        <link href="css/bootstrap.min.css" rel="stylesheet">
                        <link href="css/all.min.css" rel="stylesheet">
                        <link href="css/style.css" rel="stylesheet">
                </noscript>
                <script nonce="' . htmlspecialchars($nonce) . '">
                        document.getElementById("bootstrap-css").rel = "stylesheet";
                        document.getElementById("all-css").rel = "stylesheet";
                        document.getElementById("style-css").rel = "stylesheet";
                </script>
            </head>
        <body>
            <div class="container">
                <div class="alert alert-warning mt-5">
                    <h4 class="alert-heading">Ungültige oder nicht verifizierte Verkäufer-ID oder Hash.</h4>
                    <p>Bitte überprüfen Sie Ihre Verkäufer-ID und versuchen Sie es erneut.</p>
                    <hr>
                    <p class="mb-0">Haben Sie auf den Verifizierungslink in der E-Mail geklickt?</p>
                </div>
            </div>
            <script src="js/jquery-3.7.1.min.js" nonce="' . htmlspecialchars($nonce) . '"></script>
            <script src="js/popper.min.js" nonce="' . htmlspecialchars($nonce) . '"></script>
            <script src="js/bootstrap.min.js" nonce="' . htmlspecialchars($nonce) . '"></script>
        </body>
        </html>
        ';
    exit();
}

$email = $result->fetch_assoc()['email'];

// Fetch the current bazaar ID
$bazaar_id = get_current_bazaar_id($conn);

// Fetch max products per seller from the bazaar
$sql = "SELECT max_products_per_seller FROM bazaar WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bazaar_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "
        <!DOCTYPE html>
        <html lang='de'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
            <title>Bazaar nicht gefunden</title>
            <link href='css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body>
            <div class='row'>
                <div class='col-4 mx-auto text-center alert alert-warning mt-5'>
                    <h4 class='alert-heading'>Bitte noch etwas Geduld.</h4>
                    <p>Sie können aktuell noch nicht auf Ihre Produkte zugreifen, weil der alte Bazaar beendet ist und noch kein Datum für den nächsten Bazaar eingetragen wurde.</p>
                    <hr>
                    <p class='mb-0 text-center'>Sie glauben dass das ein Fehler ist? Melden Sie dies bitte beim Basarteam.</p>
                </div>
            </div>
            <script src='js/jquery-3.7.1.min.js'></script>
            <script src='js/popper.min.js'></script>
            <script src='js/bootstrap.min.js'></script>
        </body>
        </html>
        ";
    exit();
}

$bazaar_settings = $result->fetch_assoc();
$max_products_per_seller = $bazaar_settings['max_products_per_seller'];

// Check the current number of products for this seller
$sql = "SELECT COUNT(*) as product_count FROM products WHERE seller_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$current_product_count = $result->fetch_assoc()['product_count'];

// Handle product creation form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_product'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    if ($current_product_count >= $max_products_per_seller) {
        echo "<div class='alert alert-danger'>Sie haben die maximale Anzahl an Artikeln erreicht, die Sie erstellen können.</div>";
    } else {
        $name = $conn->real_escape_string($_POST['name']);
        $size = $conn->real_escape_string($_POST['size']);
        $price = $conn->real_escape_string($_POST['price']);

        $rules = get_bazaar_pricing_rules($conn, $bazaar_id);
        $min_price = $rules['min_price'];
        $price_stepping = $rules['price_stepping'];

        // Validate price
        if ($price < $min_price) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelector('#priceValidationModal .modal-body').textContent = 'Der eingegebene Preis ist niedriger als der Mindestpreis von ' + $min_price + ' €.';
                    $('#priceValidationModal').modal('show');
                });
            </script>";
        } elseif (fmod($price, $price_stepping) != 0) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelector('#priceValidationModal .modal-body').textContent = 'Der Preis muss in Schritten von ' + $price_stepping + ' € eingegeben werden.';
                    $('#priceValidationModal').modal('show');
                });
            </script>";
        } else {
            // Generate a unique EAN-13 barcode
            do {
                $barcode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT) . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // Ensure 12-digit barcode as string
                $checkDigit = calculateCheckDigit($barcode);
                $barcode .= $checkDigit; // Append the check digit to make it a valid EAN-13 barcode
                $sql = "SELECT id FROM products WHERE barcode=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $barcode);
                $stmt->execute();
                $result = $stmt->get_result();
            } while ($result->num_rows > 0);

            // Insert product into the database
            $sql = "INSERT INTO products (name, size, price, barcode, bazaar_id, seller_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssi", $name, $size, $price, $barcode, $bazaar_id, $seller_id);
            if ($stmt->execute() === TRUE) {
                echo "<div class='alert alert-success'>Artikel erfolgreich erstellt.</div>";
            } else {
                echo "<div class='alert alert-danger'>Fehler beim Erstellen des Artikels: " . $conn->error . "</div>";
            }
        }
    }
}

// Handle product update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $bazaar_id = get_current_bazaar_id($conn);
    $product_id = $conn->real_escape_string($_POST['product_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $size = $conn->real_escape_string($_POST['size']);
    $price = $conn->real_escape_string($_POST['price']);

    $rules = get_bazaar_pricing_rules($conn, $bazaar_id);
    $min_price = $rules['min_price'];
    $price_stepping = $rules['price_stepping'];

    // Validate price
    if ($price < $min_price) {
        $update_validation_message = "Der eingegebene Preis ist niedriger als der Mindestpreis von $min_price €.";
    } elseif (fmod($price, $price_stepping) != 0) {
        $update_validation_message = "Der Preis muss in Schritten von $price_stepping € eingegeben werden.";
    } else {
        $sql = "UPDATE products SET name=?, price=?, size=?, bazaar_id=? WHERE id=? AND seller_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdiii", $name, $price, $size, $bazaar_id, $product_id, $seller_id);
        if ($stmt->execute() === TRUE) {
            echo "<div class='alert alert-success'>Artikel erfolgreich aktualisiert.</div>";
        } else {
            echo "<div class='alert alert-danger'>Fehler beim Aktualisieren des Artikels: " . $conn->error . "</div>";
        }
    }
}

// Handle product deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $product_id = $conn->real_escape_string($_POST['product_id']);

    $sql = "DELETE FROM products WHERE id=? AND seller_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $seller_id);
    if ($stmt->execute() === TRUE) {
        echo "<div class='alert alert-success'>Artikel erfolgreich gelöscht.</div>";
    } else {
        echo "<div class='alert alert-danger'>Fehler beim Löschen des Artikels: " . $conn->error . "</div>";
    }
}

// Handle delete all products
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_all_products'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $sql = "DELETE FROM products WHERE seller_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
    if ($stmt->execute() === TRUE) {
        echo "<div class='alert alert-success'>Alle Artikel erfolgreich gelöscht.</div>";
    } else {
        echo "<div class='alert alert-danger'>Fehler beim Löschen aller Artikel: " . $conn->error . "</div>";
    }
}

// Fetch all products for the seller
$sql = "SELECT * FROM products WHERE seller_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$products_result = $stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Artikel erstellen - Verkäufernummer: <?php echo $seller_id; ?></title>
    <!-- Preload and link CSS files -->
    <link rel="preload" href="css/bootstrap.min.css" as="style" id="bootstrap-css">
    <link rel="preload" href="css/all.min.css" as="style" id="all-css">
    <link rel="preload" href="css/style.css" as="style" id="style-css">
    <noscript>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/all.min.css" rel="stylesheet">
        <link href="css/style.css" rel="stylesheet">
    </noscript>
    <script nonce="<?php echo $nonce; ?>">
        document.getElementById('bootstrap-css').rel = 'stylesheet';
        document.getElementById('all-css').rel = 'stylesheet';
        document.getElementById('style-css').rel = 'stylesheet';
    </script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <a class="navbar-brand" href="dashboard.php">Bazaar</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item active">
                    <a class="nav-link" href="seller_products.php">Artikel verwalten <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_edit.php">Konto verwalten</a>
                </li>
            </ul>
            <hr class="d-lg-none d-block">
            <ul class="navbar-nav">
                <li class="nav-item ml-lg-auto">
                    <a class="navbar-user" href="#">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-danger text-white p-2" href="logout.php">Abmelden</a>
                </li>
            </ul>
        </div>
    </nav>
	
    <div class="container">
        <h1 class="mt-5">Artikel erstellen - Verkäufernummer: <?php echo $seller_id; ?></h1>
        <div class="action-buttons">
            <form action="seller_products.php" method="post" class="w-100">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="name">Artikelname:</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="size">Größe:</label>
                        <input type="text" class="form-control" id="size" name="size">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="price">Preis:</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100" name="create_product">Artikel erstellen</button>
            </form>
        </div>
        <a href="print_barcodes.php" class="btn btn-secondary mt-3 w-100">Etiketten drucken</a>

        <h2 class="mt-5">Erstellte Artikel</h2>
        <div class="table-responsive">
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Artikelname</th>
                        <th>Größe</th>
                        <th>Preis</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($products_result->num_rows > 0) {
                        while ($row = $products_result->fetch_assoc()) {
                            $formatted_price = number_format($row['price'], 2, ',', '.') . ' €';
                            echo "<tr>
                                    <td>{$row['name']}</td>
                                    <td>{$row['size']}</td>
                                    <td>{$formatted_price}</td>
                                    <td class='text-center p-2'>
                                        <button class='btn btn-warning btn-sm edit-product-btn' 
                                            data-id='{$row['id']}' 
                                            data-name='{$row['name']}' 
                                            data-size='{$row['size']}' 
                                            data-price='{$row['price']}'>Bearbeiten</button>
                                        <form class='inline-block' action='seller_products.php' method='post'>
                                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generate_csrf_token()) . "'>
                                            <input type='hidden' name='product_id' value='{$row['id']}'>
                                            <button type='submit' name='delete_product' class='btn btn-danger btn-sm'>Löschen</button>
                                        </form>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>Keine Artikel gefunden.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <form action="seller_products.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <button type="submit" class="btn btn-danger mb-3" name="delete_all_products">Alle Artikel löschen</button>
            </form>
        </div>

        <!-- Edit Product Modal -->
        <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-labelledby="editProductModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form action="seller_products.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editProductModalLabel">Artikel bearbeiten</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="product_id" id="editProductId">
                            <div class="form-group">
                                <label for="editProductName">Artikelname:</label>
                                <input type="text" class="form-control" id="editProductName" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="editProductSize">Größe:</label>
                                <input type="text" class="form-control" id="editProductSize" name="size">
                            </div>
                            <div class="form-group">
                                <label for="editProductPrice">Preis:</label>
                                <input type="number" class="form-control" id="editProductPrice" name="price" step="0.01" required>
                            </div>
                            <div id="editProductAlert" class="alert alert-danger d-none"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                            <button type="submit" class="btn btn-primary" name="update_product">Änderungen speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Price Validation Modal -->
        <div class="modal fade" id="priceValidationModal" tabindex="-1" role="dialog" aria-labelledby="priceValidationModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="priceValidationModalLabel">Preisvalidierung</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- The message will be set dynamically via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
	
	<?php if (!empty(FOOTER)): ?>
        <footer class="p-2 bg-light text-center fixed-bottom">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-12">
                    <p class="m-0">
                        <?php echo process_footer_content(FOOTER); ?>
                    </p>
                </div>
            </div>
        </footer>
    <?php endif; ?>
	
    <script src="js/jquery-3.7.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="js/popper.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="js/bootstrap.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Attach event listeners to the edit buttons
            document.querySelectorAll('.edit-product-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    const productName = this.getAttribute('data-name');
                    const productSize = this.getAttribute('data-size');
                    const productPrice = this.getAttribute('data-price');
                    editProduct(productId, productName, productSize, parseFloat(productPrice));
                });
            });
        });
        
        function editProduct(id, name, size, price) {
            document.getElementById('editProductId').value = id;
            document.getElementById('editProductName').value = name;
            document.getElementById('editProductSize').value = size;
            document.getElementById('editProductPrice').value = price.toFixed(2);
            $('#editProductModal').modal('show');
        }
    </script>
    <?php if (isset($validation_message)) { ?>
        <script nonce="<?php echo $nonce; ?>">
            $(document).ready(function() {
                $('#priceValidationModal .modal-body').text('<?php echo $validation_message; ?>');
                $('#priceValidationModal').modal('show');
            });
        </script>
    <?php } ?>
    <?php if (isset($update_validation_message)) { ?>
        <script nonce="<?php echo $nonce; ?>">
            $(document).ready(function() {
                $('#editProductAlert').text('<?php echo $update_validation_message; ?>').removeClass('d-none');
                $('#editProductModal').modal('show');
            });
        </script>
    <?php } ?>
</body>
</html>