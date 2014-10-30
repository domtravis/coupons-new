<?
require('inc/func.php');
require('inc/generate_coupon.php');

//Pull all values from POST
$coupon_id = $_POST['coupon_id'];
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$email = $_POST['email'];
$zip = $_POST['zip'];
if($_POST['products']){
	$products_tried = implode(", ", $_POST['products']);
}
else{
	$products_tried = NULL;
}
if ($_POST['newsletter']){
	$newsletter = "1";
}else{
	$newsletter = NULL;
}

//Find out coupon expiration date (15 days from now)
$exp_date = strtotime(date("Y-m-d", strtotime($currentDate)) . " +15 days");
//Create unique hash using coupon id and exp date
$hash = md5($coupon_id . $exp_date);
$hash = substr($hash, -6);
$ip_address = $_SERVER['REMOTE_ADDR'];


//Check to make sure coupon exists
$stmt = $db->prepare("SELECT * FROM `dev_coupons` WHERE `id`=:id");
$stmt->bindParam(':id', $coupon_id);
$stmt->execute();
//If coupon found
if($row = $stmt->fetch()){
	$coupon_type = $row['type'];
	$coupon_product = $row['product'];
	$coupon_desc = $row['desc'];
	$coupon_png = $row['png'];
	if(!$coupon_png){
		die_with_error("Coupon PNG File Not Found.");
	}
	$coupon_upc = substr($row['upc'], -5);
}
//If coupon not found
else {
	die_with_error("Coupon not found.");
}

//Check to make sure that person hasn't already downloaded coupon
$stmt = $db->prepare("SELECT * FROM `dev_coupons_downloaded` WHERE `email`=:email AND `coupon_id`=:id");
$stmt->bindParam(':email', $email);
$stmt->bindParam(':id', $coupon_id);
$stmt->execute();
//If person has downloaded coupon
if($row = $stmt->fetch()){
	die_with_error("You have already downloaded this coupon.");
}

//Else, store in `coupons_downloaded`, send email, and show verification message
else{
	//Generate coupon PDF file
	$coupon_url = generate_coupon($coupon_png, $coupon_product, $coupon_upc, $hash);
	//Email PDF
	send_email($email, $coupon_url);
		
	//Store record in the DB
	$stmt = $db->prepare("INSERT INTO `dev_coupons_downloaded` (`coupon_id`, `first_name`, `last_name`, `email`, `zip`, `products_tried`, `newsletter`, `ip_address`, `hash`, `coupon_url`) VALUES (:id, :first_name, :last_name, :email, :zip, :products_tried, :newsletter, :ip_address, :hash, :coupon_url)");
	$stmt->bindParam(':id', $coupon_id);
	$stmt->bindParam(':first_name', $first_name);
	$stmt->bindParam(':last_name', $last_name);
	$stmt->bindParam(':email', $email);
	$stmt->bindParam(':zip', $zip);
	$stmt->bindParam(':products_tried', $products_tried);
	$stmt->bindParam(':newsletter', $newsletter);
	$stmt->bindParam(':ip_address', $ip_address);
	$stmt->bindParam(':hash', $hash);
	$stmt->bindParam(':coupon_url', $coupon_url);
	$stmt->execute();
	
	//Send to success page
	$debug = "<strong>Coupon ID: </strong>" . $coupon_id . "<br>" . 
	"<strong>Type: </strong>" . $coupon_type . "<br>" .
    "<strong>Product: </strong>" . $coupon_product. "<br>" .
    "<strong>Description: </strong>" . $coupon_desc. "<br>" .
	"<strong>First Name: </strong>" . $first_name . "<br>" . 
	"<strong>Last Name: </strong>" . $last_name . "<br>" . 
	"<strong>Email: </strong>" . $email . "<br>". 
	"<strong>Zip: </strong>" . $zip . "<br>" . 
	"<strong>Products Tried: </strong>" . $products_tried . "<br>" .
	"<strong>Newsletter: </strong>" . $newsletter . "<br>" .
	"<strong>IP Address: </strong>" . $ip_address . "<br>" . 
	"<strong>Unique Hash: </strong>" . $hash . "<br>";
	die_with_success($email, $debug);
}
?>