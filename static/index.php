<?php

/*
http://localhost/mchat/static/buyer.php?productId=1&productName=Samsung&buyerId=2&buyerName=Saeful%20Anwar
http://localhost/mchat/static/buyer.php?productId=2&productName=Iphone&buyerId=3&buyerName=Muh%20Ridwan
*/

$productId = isset($_GET['productId']) ? $_GET['productId'] : 1;
$productName = isset($_GET['productName']) ? $_GET['productName'] : "Product - " . $productId;
$sellerId = isset($_GET['sellerId']) ? $_GET['sellerId'] : 1;
$sellerName = isset($_GET['sellerName']) ? $_GET['sellerName'] : "Seller - " . $sellerId;

$buyers = [
	['id' => 2, 'name' => 'Bokir'],
	['id' => 3, 'name' => 'Otong'],
];

$host = 'http://evolab.web.id/mchat/static/'

?>

<h3>Penjual</h3>
<p>Silahkan klik <a href="<?=$host?>seller.php" target="_blank">disini</a> untuk menuju ke chat penjual</p>

<h3>Pembeli</h3>
<p>Silahkan pilih salah satu pembeli dibawah ini untuk melakukan chat dengan penjual</p>
<ol>
	<?php foreach($buyers as $key => $val){ ?>
	<li><a href="<?=$host.'buyer.php?productId='.$productId.'&productName='.$productName.'&buyerId='.$val['id'].'&buyerName='.$val['name']?>" target="_blank"><?=$val['name']?></a></li>
	<?php } ?>
</ol>
