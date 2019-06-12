<?php

class custom_classes_socialwall extends handlerclass implements custominterface
{

    public $open_servers = array('server_social_wall', 'server_update_socialwall');

    private $overzicht_pagina_link = ''; // pagina link van de overzichtspagina NVT
    private $overzicht_pagina_id; // overzichtpagina NVT

    private $e;

    private $fb;

    function __construct(){
        global $e;
        $this->e = $e;
    }

    function init(){
        if(is_object($this->fb))
            return;


        $path = CLIENT_CONFIG_CORE_PLUGINS_MAP;

        //Roep de autoload aan.
        require_once $path . 'facebook/autoload.php';

    }

    /**
     * Controleren of deze class verantwoordelijk is voor de afhandeling van een token.
     * Alleen tokens die beginnen {custom:mijn_token}
     *
     * @param string    $element  token
     *
     * @return boolean
     */
    function template_check_element($element){
        return ($element=='custom:socialwall');
    }

    /**
     * Geeft output als de methode template_check_element true heeft geretourneerd
     *
     * @param string    $element  token
     * @param array     $pagina_regel_row   pagina eigen velden
     * @param array     $constanten_row     velden die op iedere pagina beschikbaar zijn
     * @param array     $pagina_row         alle eigenschappen van de pagina
     *
     * @return string
     */
    function template_get_layout($element, $pagina_regel_row, $constanten_row, $pagina_row){
        $this->init();
        return $this->get_layout();
    }

    /**
     * Functie om te kijken of de onbekende URL ergens anders aan toebehoort
     *
     * @param string    $page_raw     URL request ruw
     * @param array     $page_parts   URL request bewerkt
     * @param array     $page         pagina_link volgens het systeem
     *
     * @return boolean|string  geeft een string als er output is, anders false
     */
    function check_pagina_link($page_raw, $page_parts, $page){
        /* NVT
        if(is_array($page_parts) && count($page_parts)==2 && isset($page_parts[0]) && isset($page_parts[1]) && $page_parts[0]==$this->overzicht_pagina_link){

            $link = $page_parts[1];
            $parts = explode('-', $link);

            $id = false;
            if(is_array($parts) && count($parts)>1 && is_numeric($parts[0])){
                $id = $parts[0];

            } else {
                return false;
            }

            return $this->detail_pagina($id);
        }*/
        return false;
    }

    /**
     * overzicht pagina
     *
     * @return string
     */
    private function get_layout(){
        $this->init();                                              //Aanroepen.
        $rows = $this->getFacebookFeed();                           //Roep de Facebook feed aan.
        if (count($rows) == 0) {
            $this->notifcationMail();
        }
        $layout = '';                                               //$layout initialiseren.
        $layout .= '<div class="timeline clearfix">';               //Begin van de Social Wall HTML.
        $i = 0;                                                     //$i wordt gebruikt voor modulo. Zodat berichten afwisselen.
        $x = 0;                                                     //$x wordt gebruikt voor om de active class aan een carousel item te geven.
        $laatste_datum ='';                                         //$laatste datum wordt ingevuld met de geloopte datum om te zien of de waardes gelijk zijn.

        foreach ($rows[0] as $row) {
            $bericht_id = $row['bericht_id'];                       //Identifier van de database row.
            $platform = $row['platform'];                           //Type social platform.
            $bericht = $row['bericht'];                             //Het bericht van de social media post.
            $datum_tijd = $row['datum'];                            //Tijd en datum van het bericht.
            $tijd = $this->getTijd($datum_tijd);                    //Functie wordt aangeroepen om de tijd te formateren naar de huidige tijdzone.
            $datum = $this->getDatum($datum_tijd);                  //Functie wordt aangeroepen om de datum te formateren naar de huidige tijdzone en taal.
            $media[0] = $this->getFacebookMediaFeed($bericht_id);   //Het aanroepen van de bijlages van social media berichten. Komt in een array terug.
            $afbeelding = $media[0][0];                             //Alle afbeeldingen in de $media array.
            $video = $media[0][0][1];                               //Alle videos in de $media array.

            //Wanneer true, laat geen bericht zien in de social wall.
            if ($bericht == null && $afbeelding[0]['afbeelding'] == null && $video['video'] == null) {
                $layout .= '';
            } else {

                //Als $laatste_datum gelijk is aan $datum, groepeer de berichten in dezelfde date-label.
                if ($laatste_datum != $datum) {
                    $layout .= '<div class="timeline-date-label clearfix">' . $datum . '</div>';
                }

                //Wanneer het een even getal is, sorteer berichten link. Oneven getallen gaan aan de rechterkant. $i wordt na elke foreach opgehoogt.
                if ($i % 2 == 0) {
                    $layout .= '<div class="timeline-item">';
                } else {
                    $layout .= '<div class="timeline-item pull-right">';
                }

                $layout .= '<article class="blogpost shadow light-gray-bg bordered">';

                //Als $afbeelding leeg is, niks doen.
                if ($afbeelding[0]['afbeelding'] == null) {
                    $layout .= '';
                } elseif (sizeof($afbeelding) <= 1) {

                    //Wanneer er 1 afbeelding is als bijlage, voeg het als een normale image toe aan het bericht.
                    $layout .= '<div class="overlay-container">
                                    <img src="' . $afbeelding[0]['afbeelding'] . '" style="width: 100%;">
                                </div>';

                    //Wanneer $video niet null is, laat video zien en laat de $afbeelding achterwege.
                }elseif ($video['video'] != null) {

                    $layout .= '<div class="embed-responsive embed-responsive-16by9">
                                    <video controls>
                                       <source src="' . $video['video'] . '" style="width: 100%;">
                                    </video>
                                </div>';

                    //Wanneer er meer dan 1 afbeelding is, maak er een carousel van.
                } elseif (sizeof($afbeelding) >= 2) {
                    $layout .= '<div id="carousel-blog-post" class="carousel slide" data-ride="carousel">
                                    <div class="carousel-inner" role="listbox">';

                    foreach ($afbeelding as $a) {
                        //Wanneer $x gelijk staat aan nul, geef de call "item active", anders "active"
                        if ($x == 0) {
                            $layout .= '<div class="item active">
                                        <div class="overlay-container">
                                            <img src="' . $a['afbeelding'] . '" style="width: 100%;">
                                        </div>
                                    </div>';
                        } else {
                            $layout .= '<div class="item">
                                        <div class="overlay-container">
                                            <img src="' . $a['afbeelding'] . '" style="width: 100%;">
                                        </div>
                                    </div>';
                        }
                        $x++;
                    }
                    $x = 0;
                    $layout .= '</div></div>';
                }

                $layout .= '<header>
                                <div class="post-info">
                                    <span class="post-date"><i class="icon-clock"></i> ' . $tijd . '</span>
                                    <span class="post-date"><i class="icon-calendar"></i> ' . $datum . '</span>
                                    <span class="post-date"><i class="icon-facebook"></i> Gepost via ' . $platform. '</span>
                                </div>
                            </header>
                            <div class="blogpost-content">
                                <p>' . $bericht . '</p>
                            </div>
                        </article>
                    </div>';

                $i++;
            }
            //Maak $laatste_datum de meest recente geloopte datum.
            $laatste_datum = $datum;
        }
        $layout .= '</div>';

        return $layout;
    }

    /**
     * detail pagina
     *
     * @return string
     */
    private function detail_pagina($id){
        return 'detail pagina';

    }

    // https://socialwall.bswerkplaats.nl/index.php?server=server_social_wall
    // Deze URL wordt aangeroepen via CRON om de gegevens te updaten.
    public function server_social_wall(){
        echo $this->facebookTijdlijn();
        return 'Gelukt';
    }

    //Aan de hand van $bericht_id wordt er gezocht naar een database row.
    private function zoekNaarBericht($bericht_id) {
        $sql = "SELECT `bericht_id` FROM `custom_socialwall` WHERE `bericht_id`=:bericht_id";
        $params = array(':bericht_id' => $bericht_id);
        $row = $this->e->dbObj->getRow($sql, $params);
        return $row;
    }

    //Deze functie update als er een database row is gevonden door zoekNaarBericht();
    private function updateFacebook($bericht_id, $platform, $bericht, $datum) {
        $params = array(':platform' => $platform, ':bericht' => $bericht, ':datum' => $datum, ':bericht_id' => $bericht_id);
        list($keys, $values) = $this->e->dbObj->buildInsertLine($params);
        $this->e->dbObj->execute("SET character set utf8mb4");
        $this->e->dbObj->execute("SET names utf8mb4");
        $sql = "UPDATE `custom_socialwall` SET `platform`=:platform, `bericht`=:bericht, `datum`=:datum WHERE `bericht_id`=:bericht_id";
        $post_id = $this->e->dbObj->insertRow($sql, $params);
    }

    //Deze functie update als er een database row is gevonden door zoekNaarBericht();
    private function updateFacebookMedia($bericht_id, $afbeelding, $video) {
        $params = array(':bericht_id' => $bericht_id, ':afbeelding' => $afbeelding, ':video' => $video);
        list($keys, $values) = $this->e->dbObj->buildInsertLine($params);
        $this->e->dbObj->execute("SET character set utf8mb4");
        $this->e->dbObj->execute("SET names utf8mb4");
        $sql = "UPDATE `custom_socialwall_media` SET `afbeelding`=:afbeelding, `video`=:video WHERE `bericht_id`=:bericht_id";
        $post_id = $this->e->dbObj->insertRow($sql, $params);
    }

    //Als zoekNaarBericht 'null' teruggeeft wordt er een insert gedaan.
    private function facebookToDatabase($bericht_id, $platform, $bericht, $datum){
        $params = array(':platform' => $platform, ':bericht' => $bericht, ':datum' => $datum, ':bericht_id' => $bericht_id);
        list($keys, $values) = $this->e->dbObj->buildInsertLine($params);
        $this->e->dbObj->execute("SET character set utf8mb4");
        $this->e->dbObj->execute("SET names utf8mb4");
        $sql = "INSERT INTO `custom_socialwall` (".$keys.") VALUES (".$values.")";
        $post_id = $this->e->dbObj->insertRow($sql, $params);
        return $post_id;
    }

    //Als zoekNaarBericht 'null' teruggeeft wordt er een insert gedaan.
    private function facebookMediaToDatabase($bericht_id, $afbeelding, $video){
        $params = array(':bericht_id' => $bericht_id, ':afbeelding' => $afbeelding, ':video' => $video);
        list($keys, $values) = $this->e->dbObj->buildInsertLine($params);
        $this->e->dbObj->execute("SET character set utf8mb4");
        $this->e->dbObj->execute("SET names utf8mb4");
        $sql = "INSERT INTO `custom_socialwall_media` (".$keys.") VALUES (".$values.")";
        $insert_id = $this->e->dbObj->insertRow($sql, $params);
    }

    //Haalt de hele feed op qua berichten.
    private function getFacebookFeed() {
        $sql = "SELECT * FROM custom_socialwall";
        $this->e->dbObj->execute("SET character set utf8mb4");
        $this->e->dbObj->execute("SET names utf8mb4");
        $rows = $this->e->dbObj->getRows($sql);
        return $rows;
    }

    //Haalt de bijlages op van de Facebook feed.
    private function getFacebookMediaFeed($bericht_id) {
        $sql = "SELECT `afbeelding`, `video` FROM `custom_socialwall_media` WHERE `bericht_id`=:bericht_id";
        $params = array(':bericht_id' => $bericht_id);
        $media = $this->e->dbObj->getRows($sql, $params);
        return $media;
    }

    //Converteert de tijd naar bijv. 05:50
    private function getTijd($datum_tijd) {
        date_default_timezone_set('Europe/London');
        $tijd = date('H:i', strtotime($datum_tijd));
        return $tijd;
    }

    //Converteert de datum van Y-m-d naar bijv. 21 augustus 2019.
    private function getDatum($datum_tijd) {
        date_default_timezone_set('Europe/London');
        setlocale(LC_ALL, 'nl_NL');
        $datum = strftime('%e %B %Y', strtotime($datum_tijd));
        return $datum;
    }

    private function notifcationMail()
    {

        $naar = "martijnvsleen@gmail.com"; //Ontvanger
        $onderwerp = "[Database] Geen gegevens gevonden"; //Het onderwerp.
        $bericht = ''; //Bericht definiëren
        $headers  = 'MIME-Version: 1.0' . "\r\n"; //Mail headers instellen
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'Content-Transfer-Encoding: 8bit\n\n';
        $headers .= 'Van: crm@bswerkplaats.nl';

        //Het bericht en de opmaak
        $bericht .= '<h2>Uw social feed is leeg!</h2>
                        Voeg berichten toe aan uw social feed. Mocht het probleem blijven aanhouden neem contact op met BS Connect.';

        mail($naar,$onderwerp,$bericht,$headers);
    }

    private function tokenMail($accessToken)
    {

        $naar = "martijnvsleen@gmail.com"; //Ontvanger
        $onderwerp = "Access token is verlopen."; //Het onderwerp.
        $bericht = ''; //Bericht definiëren
        $headers  = 'MIME-Version: 1.0' . "\r\n"; //Mail headers instellen
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'Content-Transfer-Encoding: 8bit\n\n';
        $headers .= 'Van: crm@bswerkplaats.nl';

        //Het bericht en de opmaak
        $bericht .= '<h2>Uw Facebook token is verlopen.</h2>
                        <strong>Uw huidige token:</strong>' . $accessToken . '<br /> <br />
                        U kunt uw token handmatig weer verversen. Ga naar 
                        <a href="https://developers.facebook.com/tools/explorer">Graph API Explorer</a> en klik op "get token" en vernieuw uw token.<br/>
                        Voer de ververste token in via de BS Manager en binnen enkele minuten wordt uw Facebook feed weer opgehaald.';

        mail($naar,$onderwerp,$bericht,$headers);
    }

    private function mailError($foutbericht) {
        $naar = "martijnvsleen@gmail.com"; //Ontvanger
        $onderwerp = "Kritieke fout"; //Het onderwerp.
        $bericht = ''; //Bericht definiëren
        $headers  = 'MIME-Version: 1.0' . "\r\n"; //Mail headers instellen
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'Content-Transfer-Encoding: 8bit\n\n';
        $headers .= 'Van: crm@bswerkplaats.nl';

        //Het bericht en de opmaak
        $bericht .= '<h2>Een kritieke fout heeft zich voorgedaan op de Social Wall.</h2>'.$foutbericht;

        mail($naar,$onderwerp,$bericht,$headers);
    }


    //Hier wordt de insert of update uitgevoerd door server_social_wall
    private function facebookTijdlijn() {
        //Gegevens instellen.
        $this->fb = new \Facebook\Facebook([
            'app_id' =>  '508150623058325',
            'app_secret' => '1f149afcfe771f43b83c91c96c7c7503',
            'default_graph_version' => 'v3.3',
        ]);

        //Access token for accessing Facebook.
        $accessToken = 'EAAHOKQlhOZAUBAJhhb9DEQxMo8ftWsadaMkyqUfY3glFA7MDwIrWlEzEhrKNNjjy0RexTb5RUDSj5wmqdiOZCoNN8cbI7lHxcZCZA26tL5NwZALFUhZBbcJsb0Rs9CkcQPTSZBsqAqwQcuTutjukOih5WSPRM428lU3tI3yXFiRqaZANUyCNQHH5DVdyJBu5qXLgAZCdyAcWRtQZDZD';

        //Facebook berichten aanroepen, anders een error teruggeven.
        try {
            $response = $this->fb->get('me/posts', $accessToken);
            $mediaBericht = $this->fb->get('me/posts?fields=attachments', $accessToken);
        } catch(\Facebook\Exceptions\FacebookResponseException $e) {
            // Wanneer een fout plaatsvind.
            echo 'Graph returned an error: ' . $e->getMessage();
            $this->tokenMail($accessToken);
            exit;
        } catch(\Facebook\Exceptions\FacebookSDKException $e) {
            //Wanneer de validatie is mislukt.
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            $foutbericht = $e->getMessage();
            $this->mailError($foutbericht);
            exit;
        }

        //Variabelen instellen
        $posts = $response->getGraphEdge(); //Berichten
        $bijlage = $mediaBericht->getGraphEdge(); //Berichten met afbeeldingen /videos.
        $bijlageBericht = json_decode($bijlage, true);

        foreach(json_decode($posts, true) as $p) {
            $bericht_id = $p['id'];                                 //Het Facebook bericht ID.
            $platform = 'Facebook';                                 //Platform is Facebook.
            $bericht = $p['message'];                               //De content van de Facebook bericht.
            $datum = $p['created_time']['date'];                    //Wanneer het bericht is aangemaakt/gepost.
            $afbeelding = null;                                     //$afbeelding is standaard null, als het bericht een bijlage heeft wordt dit overschreven.
            $video = null;                                          //$video is standaard null, als het bericht een bijlage heeft wordt dit overschreven.
            $database_id = $this->zoekNaarBericht($bericht_id);     //Via het bericht ID wordt er een database call gedaan om te zoeken of het id al bestaat.


            //Als het bericht_id nog niet bestaat doe een insert.
            //Als het bericht_id al bestaat ga naar else en update.
            if ($database_id[0]['bericht_id'] == null) {
                $post_id = $this->facebookToDatabase($bericht_id, $platform, $bericht, $datum);

                foreach ($bijlageBericht as $bijlage) {
                    if ($p['id'] == $bijlage['id']) {

                        //Check of er bijlagen zijn.
                        if ($bijlage['attachments'] != null) {

                            //Kijken of er meer dan 1 afbeelding in het bericht zit.
                            if ($bijlage['attachments'][0]['subattachments'] != null) {
                                foreach ($bijlage['attachments'][0]['subattachments'] as $media) {
                                    $afbeelding = $media['media']['image']['src'];

                                    $this->facebookMediaToDatabase($bericht_id, $afbeelding, $video);
                                }
                            }

                            //Kijken of het 1 afbeelding is.
                            if ($bijlage['attachments'][0]['media'] != null) {
                                $afbeelding = $bijlage['attachments'][0]["media"]["image"]["src"];
                                $this->facebookMediaToDatabase($bericht_id, $afbeelding, $video);
                            }

                            //Kijken of er een video in het bericht zit.
                            if ($bijlage['attachments'][0]['media']['source'] != null) {
                                $video = $bijlage['attachments'][0]['media']['source'];
                                $this->facebookMediaToDatabase($bericht_id, $afbeelding, $video);
                            }
                        }

                    }
                }
            } else {
                foreach ($bijlageBericht as $bijlage) {
                    $this->updateFacebook($bericht_id, $platform, $bericht, $datum);
                    if ($p['id'] == $bijlage['id']) {

                        //Check of er bijlagen zijn.
                        if ($bijlage['attachments'] != null) {

                            //Kijken of er meer dan 1 afbeelding in het bericht zit.
                            if ($bijlage['attachments'][0]['subattachments'] != null) {
                                foreach ($bijlage['attachments'][0]['subattachments'] as $media) {
                                    $afbeelding = $media['media']['image']['src'];

                                    $this->updateFacebookMedia($bericht_id, $afbeelding, $video);
                                }
                            }

                            //Kijken of het 1 afbeelding is.
                            if ($bijlage['attachments'][0]['media'] != null) {
                                $afbeelding = $bijlage['attachments'][0]["media"]["image"]["src"];
                                $this->updateFacebookMedia($bericht_id, $afbeelding, $video);
                            }

                            //Kijken of er een video in het bericht zit.
                            if ($bijlage['attachments'][0]['media']['source'] != null) {
                                $video = $bijlage['attachments'][0]['media']['source'];
                                $this->updateFacebookMedia($bericht_id, $afbeelding, $video);
                            }
                        }
                    }
                }
            }
        }
    }
}
