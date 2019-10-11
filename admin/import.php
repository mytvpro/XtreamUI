<?php
include "functions.php";
if (!isset($_SESSION['user_id'])) {
  header("Location: ./login.php");
  exit;
}

$imported = false;
$rArray = array("type" => 1, "added" => time(), "read_native" => 0, "stream_all" => 0, "direct_source" => 0, "gen_timestamps" => 0, "transcode_attributes" => array(), "stream_display_name" => "", "stream_source" => array(), "category_id" => $_POST['category_id'], "stream_icon" => "", "notes" => "", "custom_sid" => "", "custom_ffmpeg" => "", "transcode_profile_id" => 0, "enable_transcode" => 0, "auto_restart" => "[]", "allow_record" => 1, "rtmp_output" => 0, "epg_id" => 0, "channel_id" => "", "epg_lang" => "", "tv_archive_server_id" => 0, "tv_archive_duration" => 0, "redirect_stream" => 1, "delay_minutes" => 0, "external_push" => array());

if (isset($_POST["direct_source"])) {
  $rArray["direct_source"] = 1;
  unset($_POST["direct_source"]);
} else {
  $rArray["direct_source"] = 0;
}

if (isset($_POST["probesize_ondemand"])) {
  $rArray["probesize_ondemand"] = intval($_POST["probesize_ondemand"]);
  unset($_POST["probesize_ondemand"]);
} else {
  $rArray["probesize_ondemand"] = 0;
}

if (isset($_POST["import"])) {
  $file = '';
  if (!empty($_POST['m3u_url'])) {
    $file = file_get_contents($_POST['m3u_url']);
  } else if (!empty($_FILES['m3u_file']['tmp_name'])) {
    $file = file_get_contents($_FILES['m3u_file']['tmp_name']);
  }

  preg_match_all('/(?P<tag>#EXTINF:)|(?:(?P<prop_key>[-a-z]+)=\"(?P<prop_val>[^"]+)")|(?<display_name>,[^\r\n]+)|(?<url>http[^\s]+)/', $file, $match);
  $count = count($match[0]);
  $result = [];
  $index = -1;

  for ($i = 0; $i < $count; $i++) {
    $item = $match[0][$i];

    if (!empty($match['tag'][$i])) {
      //is a tag increment the result index
      ++$index;
    } elseif (!empty($match['prop_key'][$i])) {
      //is a prop - split item
      $result[$index][$match['prop_key'][$i]] = $match['prop_val'][$i];
    } elseif (!empty($match['display_name'][$i])) {
      //is a prop - split item
      $result[$index]['display_name'] = ltrim($item, ',');
    } elseif (!empty($match['url'][$i])) {
      $result[$index]['url'] = $item;
    }
  }

  $rCols = implode(',', array_keys($rArray));
  $count = 0;
  foreach ($result as $stream) {
    $rArray["stream_display_name"] = $stream["display_name"];
    $rArray["stream_source"] = "[\"".$stream["url"]."\"]";
    if (isset($stream["tvg-logo"])) {
      $rArray["stream_icon"] = $stream["tvg-logo"];
    }
    $rArray["channel_id"] = $stream["tvg-id"];

    foreach (array_values($rArray) as $rValue) {
      isset($rValues) ? $rValues .= ',' : $rValues = '';
      if (is_array($rValue)) {
        $rValue = json_encode($rValue);
      }
      if (is_null($rValue)) {
        $rValues .= 'NULL';
      } else {
        $rValues .= '\'' . $db->real_escape_string($rValue) . '\'';
      }
    }
    $rQuery = "INSERT INTO `streams`(" . $rCols . ") VALUES (" . $rValues . ");";
    $db->query($rQuery);
    $rInsertID = $db->insert_id;
    ++$count;
    $rOnDemandArray = Array();

    if (isset($_POST["on_demand"])) {
        foreach ($_POST["on_demand"] as $rID) {
            $rOnDemandArray[] = $rID;
        }
    }

    if (isset($_POST["server_tree_data"])) {
      $rServerTree = json_decode($_POST["server_tree_data"], True);
      foreach ($rServerTree as $rServer) {
          if ($rServer["parent"] <> "#") {
              $rServerID = intval($rServer["id"]);

              if ($rServer["parent"] == "source") {
                  $rParent = "NULL";
              } else {
                  $rParent = intval($rServer["parent"]);
              }
              if (in_array($rServerID, $rOnDemandArray)) {
                  $rOD = 1;
              } else {
                  $rOD = 0;
              }
              $db->query("INSERT INTO `streams_sys`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES(".intval($rInsertID).", ".$rServerID.", ".$rParent.", ".$rOD.");");
          }
      }
    }

    unset($rValues);
  }
  $imported = true;

}

$rServerTree = Array();
$rOnDemand = Array();
$rServerTree[] = Array("id" => "source", "parent" => "#", "text" => "<strong>Stream Source</strong>", "icon" => "mdi mdi-youtube-tv", "state" => Array("opened" => true));

foreach ($rServers as $rServer) {
    $rServerTree[] = Array("id" => $rServer["id"], "parent" => "#", "text" => $rServer["server_name"], "icon" => "mdi mdi-server-network", "state" => Array("opened" => true));
}


include "header.php"; ?>
<div class="wrapper boxed-layout">
  <div class="container-fluid">
    <!-- start page title -->
    <div class="row">
      <div class="col-12">
        <div class="page-title-box">

          <h4 class="page-title">Import Streams</h4>
        </div>
      </div>
    </div>


    <!-- end page title -->

    <div class="row">
      <div class="col-xl-12">
        <div class="card">
          <div class="card-body">
            <form enctype="multipart/form-data" action="./import.php" method="POST" id="import">
              <input type="hidden" name="server_tree_data" id="server_tree_data" value="" />
              <div class="form-group row mb-4">
                <label class="col-md-4 col-form-label" for="m3u_url">M3U URL</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" id="m3u_url" name="m3u_url" />
                </div>
              </div>
              <div class="form-group row mb-4">
                <label class="col-md-4 col-form-label" for="m3u_file">M3U File</label>
                <div class="col-md-8">
                  <input type="file" id="m3u_file" name="m3u_file" />
                </div>
              </div>
              <div class="form-group row mb-4">
                <label class="col-md-4 col-form-label" for="category_id">Category Name</label>
                <div class="col-md-8">
                  <select name="category_id" id="category_id" class="form-control" data-toggle="select2">
                    <?php foreach ($rCategories as $rCategory) { ?>
                      <option <?php if (isset($rStream)) {
                                  if (intval($rStream["category_id"]) == intval($rCategory["id"])) {
                                    echo "selected ";
                                  }
                                } else if ((isset($_GET["category"])) && ($_GET["category"] == $rCategory["id"])) {
                                  echo "selected ";
                                } ?>value="<?= $rCategory["id"] ?>"><?= $rCategory["category_name"] ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
              <div class="form-group row mb-4">
                  <label class="col-md-4 col-form-label" for="servers">Server Tree</label>
                  <div class="col-md-8">
                      <div id="server_tree"></div>
                  </div>
              </div>
              <div class="form-group row mb-4">
                  <label class="col-md-4 col-form-label" for="on_demand">On Demand</label>
                  <div class="col-md-8">
                      <select id="on_demand" name="on_demand[]" class="form-control select2-multiple" data-toggle="select2" multiple="multiple" data-placeholder="Choose ...">
                          <?php foreach($rServers as $rServerItem) { ?>
                              <option value="<?=$rServerItem["id"]?>"><?=$rServerItem["server_name"]?></option>
                          <?php } ?>
                      </select>
                  </div>
              </div>
              <div class="form-group row mb-4">
                <label class="col-md-4 col-form-label" for="direct_source">Direct Source <i data-toggle="tooltip" data-placement="top" title="" data-original-title="Don't run source through Xtream Codes, just redirect instead." class="mdi mdi-information"></i></label>
                <div class="col-md-2">
                    <input name="direct_source" id="direct_source" type="checkbox" data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                </div>
              </div>
              <div class="form-group row mb-4">
                <label class="col-md-4 col-form-label" for="probesize_ondemand">On Demand Probesize <i data-toggle="tooltip" data-placement="top" title="" data-original-title="Adjustable probesize for ondemand streams. Adjust this setting if you experience issues with no audio." class="mdi mdi-information"></i></label>
                <div class="col-md-2">
                    <input type="text" class="form-control" id="probesize_ondemand" name="probesize_ondemand" value="512000">
                </div>
              </div>

              <input name="import" type="submit" class="btn btn-primary" value="Import" />
            </form>
          </div> <!-- end col -->
        </div> <!-- end row -->
      </div>
    </div>
  </div> <!-- end container -->
</div>
<!-- end wrapper -->
<!-- Footer Start -->
<footer class="footer">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-12  text-center">Xtream Codes - Admin UI</div>
    </div>
  </div>
</footer>


<!-- end Footer -->
<!-- Vendor js -->
<script src="assets/js/vendor.min.js"></script>
<script src="assets/libs/select2/select2.min.js"></script>
<script src="assets/libs/jquery-toast/jquery.toast.min.js"></script>
<!-- Plugins js-->
<script src="assets/libs/switchery/switchery.min.js"></script>
<script src="assets/libs/twitter-bootstrap-wizard/jquery.bootstrap.wizard.min.js"></script>
<!-- Tree view js -->
<script src="assets/libs/treeview/jstree.min.js"></script>
<script src="assets/js/pages/treeview.init.js"></script>
<script src="assets/js/pages/form-wizard.init.js"></script>
<!-- App js-->
<script src="assets/js/app.min.js"></script>

<script>
(function($) {
  $.fn.inputFilter = function(inputFilter) {
    return this.on("input keydown keyup mousedown mouseup select contextmenu drop", function() {
      if (inputFilter(this.value)) {
        this.oldValue = this.value;
        this.oldSelectionStart = this.selectionStart;
        this.oldSelectionEnd = this.selectionEnd;
      } else if (this.hasOwnProperty("oldValue")) {
        this.value = this.oldValue;
        this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
      }
    });
  };
}(jQuery));
$(document).ready(function() {
  var imported = "<?= $imported?>";
  if(imported) {
    $.toast("<?= $count ?> streams have been imported.");
  }
  $("#probesize_ondemand").inputFilter(function(value) { return /^\d*$/.test(value); });
  $('select').select2({width: '100%'})
  var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
  elems.forEach(function(html) {
    var switchery = new Switchery(html);
  });
  $('#server_tree').jstree({ 'core' : {
      'check_callback': function (op, node, parent, position, more) {
          switch (op) {
              case 'move_node':
                  if (node.id == "source") { return false; }
                  return true;
          }
      },
      'data' : <?=json_encode($rServerTree)?>
  }, "plugins" : [ "dnd" ]
  });
  $("#import").submit(function(e){
    $("#server_tree_data").val(JSON.stringify($('#server_tree').jstree(true).get_json('#', {flat:true})));
    rPass = false;
    $.each($('#server_tree').jstree(true).get_json('#', {flat:true}), function(k,v) {
        if (v.parent == "source") {
            rPass = true;
        }
    });
    if (rPass == false) {
        e.preventDefault();
        $.toast("Select at least one server.");
    }
  });
});
</script>
</body>
</html>