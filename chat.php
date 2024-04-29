<?php

// ************* Initialisation 

$db=connexion_bdd();
ini_set("xdebug.overload_var_dump", "off");
demarrer_session();
header('Access-Control-Allow-Origin: *');

// ************* Quelle action executer ? 

switch($_GET['action'] ?? ''){
case 'messages':
	messages();
	break;
case 'nom':
	nom();
	break;
case 'changer-nom':
	changer_nom();
	break;
case 'envoyer':
	envoyer();
	break;
case 'reinitialiser':
	reinitialiser();
	break;
case 'test':
	echo 'test ok';
	break;
default:
	echo 'Vous n\'avez pas specifié d\'action';
}

// ************* Les actions

function messages(){
	global $db;
	$req=$db->prepare("SELECT messages.*,utilisateurs.nom FROM messages,utilisateurs ".
					  "WHERE messages.utilisateur=utilisateurs.id ORDER BY messages.id ASC");
	$req->execute();
	$messages=$req->fetchAll(PDO::FETCH_ASSOC);
	foreach($messages as &$message){
		$message['aMoi']=$message['utilisateur']==$_SESSION['utilisateur'];
	}
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($messages);
}

function nom(){
	global $db;
	$nom=get_nom();
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['nom'=>$nom]);	
}

function changer_nom(){
	global $db;

	$req=$db->prepare("INSERT INTO messages(texte,utilisateur) VALUES(:texte,:utilisateur)");
	$req->execute(['texte'=>'"'.get_nom().'" est devenu "'.$_POST['nom'].'"','utilisateur'=>3]);

	$req=$db->prepare("UPDATE utilisateurs SET nom=:nom WHERE id=:id");
	$req->execute(['id'=>$_SESSION['utilisateur'],'nom'=>$_POST['nom']]);

	header('Content-Type: application/json; charset=utf-8');
	echo json_encode('ok');	
}

function envoyer(){
	global $db;
	$req=$db->prepare("INSERT INTO messages(texte,utilisateur) VALUES(:texte,:utilisateur)");
	$req->execute(['texte'=>$_POST['texte'],'utilisateur'=>$_SESSION['utilisateur']]);
	// Miaou!
	if((rand()%15)===0){
		$req->execute(['texte'=>rand()%2 ? 'miaou' : 'ron-ron','utilisateur'=>3]);
	}
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode('ok');	
}

function reinitialiser(){
	initialiser_bdd();
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode('ok');	
}

// ************* Les autres fonctions

function creer_utilisateur($nom=false){
	global $db;
	$req=$db->prepare("INSERT INTO utilisateurs(nom,session) VALUES(:nom,:session)");
	$req->execute(['nom'=>$nom===false ? 'anonyme' : $nom,'session'=>session_id()]);
	$id=intval($db->lastInsertId());
	return $id;
}

// Ouvrir la connexion avec la base de données.
// Si c'est la première fois, initialiser_bdd() est aussi appelée.
function connexion_bdd(){	
	global $db;
	$db=new PDO('pgsql:host=aquabdd;dbname=etudiants', '12203019', '070826288AJ');
	// Afficher les messages d'erreur
	$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
	// Si la table messages n'existe pas encore, appeler initialiser_bdd()
	$result = @$db->query("SELECT 1 FROM messages LIMIT 1");
	if($result===false){initialiser_bdd();}
	return $db;
}

// Supprime les anciennes tables et les recrée à nouveau.
// Crée aussi quelques messages et utilisateurs.
function initialiser_bdd()	{
	global $db;
	// Supprimer les tables si elles existent déjà
	$db->exec('DROP TABLE IF EXISTS utilisateurs');
	$db->exec('DROP TABLE IF EXISTS messages');

	// Créer les tables

	// Cette table utilisateurs a un champs "session" seulement 
	// pour gérer une situaiton particulière (voir demarrer_session())
	$db->exec('CREATE TABLE utilisateurs(
   id  SERIAL PRIMARY KEY,
   nom        TEXT      NOT NULL,
   session    TEXT      NOT NULL
);');

	$db->exec('CREATE TABLE messages(
   id  SERIAL PRIMARY KEY,
   utilisateur   INT       NOT NULL,
   texte         TEXT      NOT NULL
);');

	creer_utilisateur('Joe');
	creer_utilisateur('Leila');
	creer_utilisateur('?');

	// Remplir la table messages avec 3 messages
	$req=$db->prepare("INSERT INTO messages(utilisateur, texte) VALUES(:utilisateur, :texte)");
	$req->execute(['utilisateur'=>1,'texte'=>'Salut tout le monde.']);
	$req->execute(['utilisateur'=>1,'texte'=>"Ya quelqu'un ?"]);
	$req->execute(['utilisateur'=>2,'texte'=>'Salut.']);
}

// La session permet d'utiliser la variable $_SESSION qui est persistante entre deux appels.
// Ceci utilise un cookie de session. 
// On utilise $_SESSION ici pour identifier qui est l'utilisateur actuel.
function demarrer_session(){
	global $db;
	session_start();

	// Cas très particulier: si un utilisateur reinitialise la BDD, 
	// les autres utilisateurs resteront bloqués dans des sessions invalides. 
	// On doit donc vérifier si cet utilisateur+session existe bien encore.
	if(isset($_SESSION['utilisateur'])){
		$req=$db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE id=:id AND session=:session");
		$req->execute(['id'=>$_SESSION['utilisateur'],'session'=>session_id()]);
		$n=$req->fetchColumn();
		if($n==0){unset($_SESSION['utilisateur']);}
	}

	// Créer un utilisateur la première fois qu'un untilisateur se connecte sur cette session.
	if(!isset($_SESSION['utilisateur'])){
		$_SESSION['utilisateur']=creer_utilisateur();
		$req=$db->prepare("UPDATE utilisateurs SET nom=:nom WHERE id=:id");
		$req->execute(['id'=>$_SESSION['utilisateur'],'nom'=>'anonyme-'.$_SESSION['utilisateur']]);
	}
}

function get_nom(){
	global $db;
	$req=$db->prepare("SELECT nom FROM utilisateurs WHERE id=:id");
	$req->execute(['id'=>$_SESSION['utilisateur']]);
	return $req->fetchColumn();
}

// Pour debugger: Affiche un ou plusieurs paramètres dans un fichier log.
// Il faut au préalable créer le fichier "vlog" avec les droits d'écriture.
function vlog(...$args)	{
	ob_start();
	var_dump(...$args);	
	$content = ob_get_contents();
	ob_end_clean();
	file_put_contents('vlog','######'.date('r').': '.$content,FILE_APPEND);
}
