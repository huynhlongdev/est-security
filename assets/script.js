jQuery(document).ready(function ($) {
  var btn = $("#delete-php-files-btn");
  // Remove php file in upload folder
  btn.on("click", function (e) {
    e.preventDefault();
    var form = $("#delete-php-files-form");
    var data = form.serialize();
    data += "&action=delete_php_files_ajax";
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: data,
      dataType: "json",
      beforeSend: function () {
        $("#delete-php-files-btn").text("Deleting...");
      },
      success: function (res) {
        $(".files-list").html(res?.data?.remaining_files);
      },
    }).always(function () {
      $("#delete-php-files-btn").text("Delete");
    });
  });

  // Remove file log
  $(document).on("click", "#delete_debug_log", function () {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "remove_log",
      },
      dataType: "json",
      beforeSend: function () {
        $("#delete_debug_log").text("Deleting...");
      },
      success: function (res) {},
    }).always(function () {
      $("#delete_debug_log").text("Delete");
    });
  });

  //
  $("#change-prefix-btn").on("click", function () {
    const $btn = $(this);
    const $result = $("#change-prefix-result");

    $btn.prop("disabled", true).text("Processing...");
    $result.html("<p>Working...</p>");

    $.post(
      SecurityDBPrefix.ajaxurl,
      {
        action: "change_db_prefix",
        _ajax_nonce: SecurityDBPrefix.nonce,
      },
      function (response) {
        if (response.success) {
          $result.html(
            '<div class="updated notice"><p>' +
              response.data.message +
              "</p></div>"
          );
        } else {
          $result.html(
            '<div class="error notice"><p>' +
              (response.data?.message || "Unknown error") +
              "</p></div>"
          );
        }
        $btn.prop("disabled", false).text("Generate");
      }
    ).fail(function () {
      $result.html(
        '<div class="error notice"><p>Request failed. Check console.</p></div>'
      );
      $btn.prop("disabled", false).text("Generate");
    });
  });

  $("#update-est-security").on("click", function () {
    //update-message notice inline notice-warning notice-alt updating-message
    console.log(">>> update");
    $(this)
      .closest("tr")
      .find(".update-message")
      .addClass("updating-message")
      .find("p")
      .html("Updating... Please wait.");

    const button = $(this).closest("tr");

    $.post(
      SecurityDBPrefix.ajaxurl,
      {
        action: "update_est_plugin_ajax",
        unlock_nonce: SecurityDBPrefix.unlock_nonce,
      },
      function (response) {
        window.location.reload();
      }
    );
  });

  $(".unlock-user-btn").on("click", function () {
    const user = $(this).data("user");
    const login_ip = $(this).data("ip");
    const button = $(this);

    $.post(
      SecurityDBPrefix.ajaxurl,
      {
        action: "unlock_user_ajax",
        username: user,
        login_ip: login_ip,
        unlock_nonce: SecurityDBPrefix.unlock_nonce,
      },
      function (response) {
        if (response.success) {
          button.closest("tr").fadeOut();
        } else {
          alert(response.data);
        }
      }
    );
  });
});
