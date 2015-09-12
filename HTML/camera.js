        $(document).ready(function() {
		$("#helpBtn").click(function() {
			$("#help").slideToggle();
		});

		$(".imgLink").click(function(e) {
			var imgThumb = $(this).attr('src').replace("thumb", "full");
			 e.preventDefault();

			$("#image").find("p").html("<img src='" + imgThumb + "'>");
			$("#image").slideDown();

			return false;
		});
	});
