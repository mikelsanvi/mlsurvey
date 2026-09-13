<?php
include_once 'utils/classname.php';
include_once 'utils/session.php';
include_once 'utils/logger.php';
include_once 'ifaces/view.php';
include_once 'utils/user.php';
startSession ();
$viewsfolder = 'views';
$classviewfile = $viewsfolder;
$classview = "";
if (isset($_GET['view'])){
	$classview = $_GET['view'];
}
else {
	if (isset($_SERVER['REDIRECT_URL']))
		$theuri = $_SERVER['REDIRECT_URL'];
	else 
		$theuri = $_SERVER['REQUEST_URI'];
	$uris = explode ('/', $theuri);
	$classview = $uris[count($uris) -1];
}
if ($classview == 'logout'){
	clearSession ();
	$classview = "";
}

if ($classview != ""){
	$classviewfile .= '/' . $classview . '.php';
}
else {
	$classview = "MainView";
}
if ($classview != 'MainView' && file_exists ($classviewfile)){
		include_once $classviewfile;
		$classview = getClassName($classview);
	}
	else {
		logMessage (LOGGER_ERROR,  "file {$classviewfile} for {$classview} does not exist");
		$classview = "MainView";
	}
	
	$view = new $classview;
	$view->doInit ();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Free Bootstrap Template | webthemez</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="" />
<meta name="author" content="http://webthemez.com" />
<!-- css -->
<link href="css/bootstrap.min.css" rel="stylesheet" />
<link href="css/fancybox/jquery.fancybox.css" rel="stylesheet">
<!--<link href="css/jcarousel.css" rel="stylesheet" />-->
<link href="css/flexslider.css" rel="stylesheet" />
<!--<link href="js/owl-carousel/owl.carousel.css" rel="stylesheet">-->
<link href="css/style.css" rel="stylesheet" />
<script src="js/jquery.js"></script> 
<?php
$view->addHead ();
?>
<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
<!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
</head>
<body>
<div id="wrapper">
	<!-- start header -->
	<header>
        <div class="navbar navbar-default navbar-static-top">
            <div class="container">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="index.php">Aquí un logo de unos 60px de altura</a>
                </div>
                <div class="navbar-collapse collapse ">
                    <ul class="nav navbar-nav">
<!--                        <li class="active"><a href="">Inicio</a></li> 
						<li><a href="admin">Administración</a></li>
						<li><a href="surveys">Consultas</a></li>-->
						<?php
						include_once 'include/menuarray.php';
						foreach ($menuarray as $key => $menuitem) {
							//var_dump($menuitem);
							$entry = "<li ";
							if ($menuitem[ML_MENU_GROUP] == $view->getMenuGroup ())
								$entry .= "class='active' ";
							$entry .= "><a href='{$menuitem[ML_MENU_LOCATION]}'>" . 
								$menuitem[ML_MENU_ENTRY] . "</a></li>";
							echo ($entry);
						}
						?>
                    </ul>
                </div>
            </div>
        </div>
	</header>
	<!-- end header -->
	 <?php
	 if (!isUser()){?>
	<section id="featured">
		<div class="row">
			<div class="col-md-3">
	 			<h2>Esta columna para el logo en grande</h2>
			</div>
			<div class="col-md-8">
	 			<h2>Esta para explicar qué pasa</h2>
				<p>Lorem ipsum dolor sit amet, unde omnis iste natus tote natus error sit voluptatem accusanti quas potenti maltam rem aperiam, eaque ipsa quae ab illitecto beatae vitae dicemo enim ipsam voluptatem quia voluptas sit aspernatur.</p>
			</div>
		</div>
	</section>
	<?php
	 }
	 ?>
<div class="featured_content">
  <div class="container">
	<?php
		
	showView ($view);
	?>
  </div>
</div>
</div> 
	<footer>
	<div class="container">
		<div class="row">
			<div class="col-lg-3">
				<div class="widget">
					<h5 class="widgetheading">Contacto</h5>
					<address>
					<strong>Por ejemplo eso</strong>
					</address>
					<p>
						<i class="icon-envelope-alt"></i> administradora@dominio.org
					</p>
				</div>
			</div>
			<div class="col-lg-3">
				<div class="widget">
					<h5 class="widgetheading">Enlaces importantes</h5>
					<ul class="link-list">
						<li><a href="#">Latest Events</a></li>
						<li><a href="#">Terms and conditions</a></li>
						<li><a href="#">Privacy policy</a></li>
					</ul>
				</div>
			</div>
			<div class="col-lg-3">
				<div class="widget">
					<h5 class="widgetheading">Últimos debates</h5>
					<ul class="link-list">
						<li><a href="#">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</a></li>
						<li><a href="#">Pellentesque et pulvinar enim. Quisque at tempor ligula</a></li>
						<li><a href="#">Natus error sit voluptatem accusantium doloremque</a></li>
					</ul>
				</div>
			</div>
			<div class="col-lg-3">
				<div class="widget">
					<h5 class="widgetheading">Últimas noticias</h5>
					<ul class="link-list">
						<li><a href="#">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</a></li>
						<li><a href="#">Pellentesque et pulvinar enim. Quisque at tempor ligula</a></li>
						<li><a href="#">Natus error sit voluptatem accusantium doloremque</a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
	<div id="sub-footer">
		<div class="container">
			<div class="row">
				<div class="col-lg-6">
					<div class="copyright">
						<p>
							<span>Powered by: <a href="https://github.com/pacoandres/mlsurvey" target="_blank">mlsurveys</a></span>
						</p>
						<p>
							<span>Special thanks to: <a href="https://github.com/PHPMailer/PHPMailer" target="_blank">PHPMailer</a>
						and <a href="https://github.com/hugerte/hugerte" target="_blank">HugeRTE</a></span>
						</p>
						<p>
							<span>Original theme: &copy; Target 2014 All right reserved. By </span><a href="http://webthemez.com" target="_blank">WebThemez</a>
						</p>
					</div>
				</div>
				<div class="col-lg-6">
					<ul class="social-network">
						<li><a href="#" data-placement="top" title="Facebook"><i class="fa fa-facebook"></i></a></li>
						<li><a href="#" data-placement="top" title="Twitter"><i class="fa fa-twitter"></i></a></li>
						<li><a href="#" data-placement="top" title="Linkedin"><i class="fa fa-linkedin"></i></a></li>
						<li><a href="#" data-placement="top" title="Pinterest"><i class="fa fa-pinterest"></i></a></li>
						<li><a href="#" data-placement="top" title="Google plus"><i class="fa fa-google-plus"></i></a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
	</footer>
</div>
<a href="#" class="scrollup"><i class="fa fa-angle-up active"></i></a>
<!-- javascript
    ================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script src="js/jquery.easing.1.3.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.fancybox.pack.js"></script>
<script src="js/jquery.fancybox-media.js"></script> 
<script src="js/portfolio/jquery.quicksand.js"></script>
<script src="js/portfolio/setting.js"></script>
<script src="js/jquery.flexslider.js"></script>
<script src="js/animate.js"></script>
<script src="js/custom.js"></script>
<!--<script src="js/owl-carousel/owl.carousel.js"></script>-->
<?php
if (isView ($view))
	$view->addJavascript ();
?>
</body>
</html>