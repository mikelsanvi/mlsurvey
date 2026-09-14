<?php
require_once "ifaces/view.php";
require_once "utils/dbutils.php";

class Surveys extends View {
    public const SURVEY_RESPONSE = "response";
    private const SURVEY_QUERY = "query";
    private const RESPONSE_VALUES = [
        'code' => "Obtener código",
        'response' => "Responder"
    ];

    function getMenuGroup (){
        return ML_MENU_GROUP_SURVEYS;
    }

    public function loadStyles (){
        ?>
        <link href="css/tablecard.css" rel="stylesheet" />
        <link href="css/button3.css" rel="stylesheet" />
        <?php
    }
    public function show (){
        try {
            $db = dbConn ();
            echo ("<h2>Consultas activas.</h2>");
            $query = $db->prepare ("SELECT surveyid, surveyname" .
                ", DATE_FORMAT(enddate,'%d/%m/%Y %T') as dend" . 
                ", DATE_FORMAT(startdate,'%d/%m/%Y %T') as dstart ". 
                "FROM {Surveys} WHERE " .
                "startdate < NOW() and enddate > NOW() " .
                "ORDER BY startdate DESC");
            $query->execute ();
            if ($query->rowCount () == 0){
                echo ("<strong>No hay consultas activas para realizar.</strong>");
            }
            else {
                $this->addActiveSurveys ($query);
            }
            $query->closeCursor ();
            echo ("<h2>Consultas finalizadas.</h2>");
            $query = $db->prepare ("SELECT surveyid, surveyname" .
                ", DATE_FORMAT(enddate,'%d/%m/%Y %T') as dend" . 
                ", DATE_FORMAT(startdate,'%d/%m/%Y %T') as dstart ". 
                "FROM {Surveys} WHERE " .
                "enddate < NOW() " .
                "ORDER BY enddate DESC");
            $query->execute ();
            if ($query->rowCount () == 0){
                echo ("<p><strong>No hay consultas finalizadas para consultar.</strong></p>");
            }
            else {
                $this->addEndedSurveys ($query);
            }
            $query->closeCursor ();
        }
        catch (Exception $e){
            echo ("<strong>Error recuperando consultas.</strong>");
            logMessage (LOGGER_ERROR, "Error {$e} loading surveys.");
        }
    }

    private function addActiveSurveys ($surveys){
        ?>
        <form id="responsesurvey" name="responsesurvey" method="POST" action="get_code">
        <script type="text/javascript">
        function getCode (surveyid){
            const survey = document.getElementById ("responseid");
            survey.value = surveyid;
            return true;
        }
        </script>
        <input type="hidden" id="responseid" name="responseid">
        <div class="card-table-container">
            <table class="card-like-table ml-stack" id="surveystable">
                <thead><tr>
                    <td>Consulta</td><td>Fecha inicio</td><td>Fecha fin</td><td>Seleccionar</td>
                </tr></thead>
                <tbody>
                <?php
                while ($survey = $surveys->fetch ()){
                    $id = $survey['surveyid'];
                    ?>
                    <tr id="<?= "response_" . $id; ?>">
                        <td><span class="username" id="svr-<?= $id; ?>"><?= $survey['surveyname']?></span></td>
                        <td data-label="Fecha inicio"><?= $survey['dstart'] ?></td>
                        <td data-label="Fecha fin"><?= $survey['dend'] ?></td>
                        <td><input type="submit" class="button-3" onclick="return getCode (<?= $id; ?>);"
                            value="Ver y participar" name="<?= self::SURVEY_RESPONSE; ?>"></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>
        </form>
        <?php
    }

    private function addEndedSurveys ($surveys){
        ?>
        <script type="text/javascript">
        function queryResults ($id){
            const query = document.getElementById ("queryid");
            query.value = $id;
            return true;
        }
        </script>
        <form id="resultsesurvey" name="resultsesurvey" method="GET" action="results">
        <input type="hidden" name="queryid" id="queryid">
        <div class="card-table-container">
            <table class="card-like-table ml-stack" id="surveystable">
                <thead><tr>
                    <td>Consulta</td><td>Fecha inicio</td><td>Fecha fin</td><td>Seleccionar</td>
                </tr></thead>
                <tbody>
                <?php
                while ($survey = $surveys->fetch ()){
                    $id = $survey['surveyid'];
                    ?>
                    <tr id="<?= "query_" . $id; ?>">
                        <td><span class="username" id="svr-<?= $id; ?>"><?= $survey['surveyname']?></span></td>
                        <td data-label="Fecha inicio"><?= $survey['dstart'] ?></td>
                        <td data-label="Fecha fin"><?= $survey['dend'] ?></td>
                        <td><input type="submit" class="button-3"
                            onclick="return queryResults (<?= $id; ?>);" value="Ver resultado"></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>
        </form>
        <?php
    }
}
