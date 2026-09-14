<?php
require_once 'ifaces/view.php';
require_once 'utils/user.php';
require_once 'utils/dbutils.php';
require_once 'utils/logger.php';

class UserManage extends View {

    private $userscount = 0;
    private const MANAGEACTION = 'manageaction';
    private const ADDACTION = 'addaction';
    private const MODIFYACTION = 'modifaction';
    private const DELACTION = 'delaction';

    function getMenuGroup (){
        return ML_MENU_GROUP_ADMIN;
    }
    
    function isMulticol (){
        startSession ();
        if (isAdmin ())
            return true;

        return false;
    }

    function leftMenu (){
        include 'include/userleftcolumn.php';
    }
    
    function show (){
        if (!isAdmin ()){
            showMain ();
            return;
        }
        if (isset($_REQUEST[self::MANAGEACTION])){
            $action = $_REQUEST[self::MANAGEACTION];
            unset ($_REQUEST[self::MANAGEACTION]);
            switch ($action) {
                case 'Añadir':
                    $this->showAddUser ();
                    return;
                    break;
                case 'Modificar':
                    $this->showModifyUser ();
                    return;
                case 'Eliminar':
                    //The warning should be previous;
                    $this->deleteUser ();
                    break;
                default:
                    logMessage (LOGGER_ERROR, "Unkown users manage action {$action}");
                    break;
            }
        }
        else if (isset($_REQUEST[self::ADDACTION])){
            $action = $_REQUEST[self::ADDACTION];
            unset ($_REQUEST[self::ADDACTION]);
            switch ($action) {
                case 'Aceptar':
                    $user = $_REQUEST['user'];
                    $passwd = $_REQUEST['passwd'];
                    try {
                    if (isset($_REQUEST['isadmin'])){
                        adduser ($user, $passwd, "A");
                    }
                    else {
                        adduser ($user, $passwd);
                    }
                    }
                    catch (Exception $e){
                        echo ("<h3>Error al añadir la usuaria</h3>");
                        logMessage (LOGGER_ERROR, "Error ading user: {$e}");
                    }
                    echo ("<strong>Usuaria {$user} añadida con éxito</strong>");
                    break;
                default:
                    break;
            }
        }
        else if (isset($_REQUEST[self::MODIFYACTION])){
            $action = $_REQUEST[self::MODIFYACTION];
            unset ($_REQUEST[self::MODIFYACTION]);
            switch ($action) {
                case 'Aceptar':
                    $this->modifyUser ();
                    break;
            }
        }
        ?>
        <div class="col-md-8">
        <h2>Gestión de usuarias</h2>
        <form id="usermanage" name="usermanage" method="POST" action="user_manage" >
        <?php
        $this->listUsers ();
        $this->showControls ();
        echo ('</form></div>');
    }

    private function listUsers (){
        try {
            $dbconn = dbConn ();
            $query = $dbconn->prepare ("SELECT userid, username from {Users} WHERE " .
                "userid <> :userid " .
                "ORDER BY username");
            $query->bindParam (':userid', $_SESSION['userid']);
            $query->execute ();
            $this->userscount = $query->rowCount ();
            if ($this->userscount == 0){
                ?>
                    <strong>Actualmente no hay usuarias para gestionar.</strong>
                <?php
                return;
            }
            ?>
            <div class="card-table-container">
            <table class="card-like-table ml-stack" id="userstable">
                <thead><tr>
                    <td>Usuaria</td><td>Seleccionar</td>
                </tr></thead>
                <tbody>
                <?php
                while ($row = $query->fetch ()){
                    $id = $row['userid'];
                    ?>
                    <tr id="<?= $id; ?>">
                        <td><span class="username" id="us-<?= $id; ?>"><?= $row['username']?></span></td>
                        <td><input type="radio" name="userid" value="<?= $id; ?>" id="rb-<?= $id; ?>"></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
</div>
<script type="text/javascript">
                const miTabla = document.getElementById("userstable");

                miTabla.addEventListener("click", function(evento) {
                    // Encuentra la fila (tr) más cercana al elemento que recibió el clic
                    const fila = evento.target.closest("tr");
  
                    if (!fila) {
                        return;
                    }
                    var userid = 'rb-' + fila.id;
                    document.getElementById (userid).checked = true;    
                });
            </script>
            <?php
            $query->closeCursor ();
        }
        catch (Exception $e){
            ?>
                <p>Error obteniendo la lista de usuarios. Contacte con soporte.</p>
            <?php
            logMessage (LOGGER_ERROR, "DB error {$e} getting users list");
        }
    }

    private function showControls (){
       ?>
       <script type="text/javascript">
        function validate_modify (){
            var selectedradio = document.querySelector('input[name="userid"]:checked');
            if (!selectedradio){
                alert ('No has seleccionado ninguna para modificar');
                return false;
            }
            return true;
        }
        function confirm_delete (){
            var selectedradio = document.querySelector('input[name="userid"]:checked');
            if (!selectedradio){
                alert ('No has seleccionado ninguna para eliminar');
                return false;
            }
            var element =  'us-' + selectedradio.value;
            var username = document.getElementById (element).innerText;
            return confirm ("¿Seguro que quieres eliminar la usuaria " + username + "?\nEsto no se puede deshacer");
        }
        </script>
       <p>
       <input type="submit" class="button-3" name="<?= self::MANAGEACTION ?>" id="add" value="Añadir">
       <?php
       if ($this->userscount > 0){
        ?>
       <input type="submit" class="button-3" name="<?= self::MANAGEACTION ?>" onclick="return validate_modify ();" id="modify" value="Modificar">
       <input type="submit" class="button-3" name="<?= self::MANAGEACTION ?>" onclick="return confirm_delete();" id="delete" value="Eliminar">
       <?php
       }
       ?>
        </p>
        <?php
       
    }

    private function showAddUser (){
        ?>
        <div class="col-md-8">
        <script type="text/javascript">
            function validate_add (){
                var username = document.getElementById ("user").value;
                if (username == ""){
                    alert ("El nombre de usuaria no puede estar vacío");
                    return false;
                }
                var pw1 = document.getElementById ('passwd').value;
                var pw2 = document.getElementById ('passwd2').value;
                if (pw1 == ""){
                    alert ("LA clave no puede estar vacía");
                    return false;
                }
                if (pw1 != pw2){
                    alert ("Las claves no coinciden");
                    return false;
                }
                return true;
            }
        </script>
        <h2>Añadir usuaria</h2>
        <form id="adduser" name="adduser" method="POST" action="user_manage" 
onload='document.getElementById("user").focus();'>
        <p>Usuaria: <input type="text" id="user" name="user" tabindex="-1"></p>
        <p>Clave: <input type="password" id="passwd" name="passwd"></p>
        <p>Confirmar clave: <input type="password" id="passwd2" name="passwd2"></p>
        <p>Es admin: <input type="checkbox" id="isadmin" name="isadmin"></p>
        <p><input class="button-3" type="submit" onclick="return validate_add ();"name="<?= self::ADDACTION ?>" id="ok" value="Aceptar">
        <input class="button-3" type="submit" name="<?= self::ADDACTION ?>" id="ok" value="Cancelar">
        </p>
                </form>
                </div>
        <?php
    }

    private function deleteUser (){
        $userid = $_REQUEST['userid'];
        try {
            $username = getUserName ($userid);
            rmUser ($userid);
            echo ("<strong>Usuaria {$username} eliminada con éxito.</strong>");
        }
        catch (Exception $e){
            echo ("<strong>Error al eliminar usuaria</strong>");
            logMessage (LOGGER_ERROR, "Error {$e} when deleting user {$userid} ({$username})");
        }
    }

    private function showModifyUser (){
        $userid = $_REQUEST['userid'];
        $dbconn = dbConn ();
        $name = getUserName ($userid);
        $isadmin = "";
        if (isAdmin ($userid)){
            $isadmin = "checked";
        }
        ?>
        <div class="col-md-8">
        <script type="text/javascript">
            function validate_mod (){
                var username = document.getElementById ("user").value;
                if (username == ""){
                    alert ("El nombre de usuaria no puede estar vacío");
                    return false;
                }
                var pw1 = document.getElementById ('passwd').value;
                var pw2 = document.getElementById ('passwd2').value;
                
                if (pw1 != pw2){
                    alert ("Las claves no coinciden");
                    return false;
                }
                return true;
            }
        </script>
        <h2>Modificar usuaria</h2>
        <form id="moduser" name="moduser" method="POST" action="user_manage" 
onload='document.getElementById("user").focus();'>
        <p>Usuaria: <input type="text" id="user" name="user" tabindex="-1" value="<?= $name ?>"></p>
        <p>Nueva clave: <input type="password" id="passwd" name="passwd" placeholder="Vacío sin cambios"></p>
        <p>Confirmar nueva clave: <input type="password" id="passwd2" name="passwd2"></p>
        <p>Es admin: <input type="checkbox" id="isadmin" name="isadmin" <?= $isadmin; ?>></p>
        <p><input class="button-3" type="submit" onclick="return validate_mod ();"name="<?= self::MODIFYACTION; ?>" id="ok" value="Aceptar">
        <input class="button-3" type="submit" name="<?= self::MODIFYACTION; ?>" id="ok" value="Cancelar">
        <input type="hidden" name="userid" value="<?= $userid; ?>">
        </p>
                </form>
                </div>
        <?php
    }
    private function modifyUser (){
        $userid = $_REQUEST['userid'];
        $newname = $_REQUEST['user'];
        $newpasswd = $_REQUEST['passwd'];
        $isadmin = "";
        if (isset ($_REQUEST['isadmin'])){
            $isadmin = "A";
        }
        try {
            alterUser ($userid, $newname, $isadmin, $newpasswd);
        }
        catch (Exception $e){
            echo ('<strong>Error al intentar modificar la usuaria</strong>');
            logMessage (LOGGER_ERROR, "Error modifying user {$e}");
        }
        echo ("<strong>Usuaria {$newname} modificada con éxito</strong>");
    }

    function loadStyles (){
        ?>
        <link href="css/tablecard.css" rel="stylesheet" />
        <link href="css/button3.css" rel="stylesheet" />
        <?php
    }
}