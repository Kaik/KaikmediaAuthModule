/**
 * Facebook login
 */

var KaikMedia = KaikMedia || {};
KaikMedia.Auth = KaikMedia.Auth || {};

( function($, Routing, Translator, Zikula) {
	KaikMedia.Auth.facebook = (function () {
		var $modal;
		// @todo - add multi accouts feature

		async function init() {
			return new Promise(async (resolve) => {
				const FB = await getScript();
				var settings = KaikMedia.Auth.Config.FB.getAll(); 
				var params = {
					appId: settings.appId,
					version: settings.version ? settings.version : 'v8.0',
					cookie : settings.cookie ? true : false,
					frictionlessRequests : settings.frictionlessRequests ? true : false,
					status: settings.status ? true : false,
					xfbml: settings.xfbml ? true : false
				};
				FB.init(params);
				loadButtons();
				resolve(FB);
			});
		};

		async function getAccessToken() {
			// var authResponse = await getAuthResponse();
			// console.log(authResponse);
			var authResponse = await getLoginStatus();
			if (authResponse.status === 'unknown' || authResponse.status === 'not_authorized') {
				authResponse = await login();
				if (authResponse.status !== 'connected') {
					// scnd try display error 
					return false;
				}
			}		

			return authResponse.authResponse.accessToken;
		}

		async function logInRegister() {
			// two accounts connected to same fb email shoudl not exist
			// that is why we have to check if there is already connected account
			// hmm 
			var loggedIn = Zikula.Config.uid;

			startStatusModal();

			try { 
				var accessToken = await getAccessToken();
				if (!accessToken) {
					throw new Error(Translator.__('Access token is missing please try again.'));
				}
				// so we have our auth response here with access token
				// we will call php side and confirm everything
				// we will collect other data as well and save them in a sesion 
				// we will use access token to talk to server side
				await startServerSideSessionAction(accessToken);

				// check maybe user is registered
				// quick check if zikula account mapped with fb user already exists
				// proceed to login if true
				var registered = await checkRegistrationStatusAction(accessToken);
					// two accounts connected to same fb email shoudl not exist
					// that is why we have to check if there is already connected account
				if (registered && loggedIn != '1' && loggedIn != registered) {
					throw new Error(Translator.__('Different zikula account is already connected. Please logout and connect to the other account'));
				} else if (registered) {
					await logInZikulaAction(accessToken, registered);
					redirectAfterLogin();

					return;
				}

				// logged in means user is already logged in and wants to connect its account to fb
				// this is allowed only on facebook settings page
				// so we will 
				if (loggedIn != '1' && loggedIn != registered) {
					await connectAccountAction(accessToken, loggedIn);
					await preferencesAction(accessToken);

					return;
				}

				// check maybe email is registered 
				// handle all cases internally
				var foundAccounts = await checkEmailStatusAction(accessToken);
				if (foundAccounts) {
					// account choser will take care of the flow
					await accountChooserAction(accessToken, foundAccounts);
					// autologin if only one account found
					// additional disable option if duplicates are allowed
					// user will be able to select create new account (duplicate)
					if (foundAccounts.length == 1 && !areMultipleAccountsAllowed()) {
						await connectLoginRedirectAction(accessToken, foundAccounts[0].uid);

						return true;
					}
					// waiting for account to be choosed
					return;
				} 

				// // register new account
				// // start registration process
				var registered = await registerZikulaAction(accessToken);
				if (registered) {
					await connectLoginRedirectAction(accessToken, registered.uid);

					return;
				} 

			} catch (errorMessage) { showError(errorMessage); return;}
		}

		async function updateAvatar() {
			try { 
				var accessToken = await getAccessToken();
				if (!accessToken) {
					throw new Error(Translator.__('Access token is missing please try again.'));
				}
				
				startStatusModal();
				await startServerSideSessionAction(accessToken);
				var response = await updateAvatarAction(accessToken);
				if (response.status == 'success') {
					$modal.modal('hide');
					$('.current-user-avatar').attr('src', response.src);
				} else {
					throw new Error(Translator.__('Unknow error occured. Please try again.'));
				}

				return;
			} catch (errorMessage) { showError(errorMessage); return;}
		}

		async function updateName() {
			try { 
				var accessToken = await getAccessToken();
				if (!accessToken) {
					throw new Error(Translator.__('Access token is missing please try again.'));
				}
				
				startStatusModal();
				await startServerSideSessionAction(accessToken);
				var response = await updateNameAction(accessToken);
				if (response.status == 'success') {
					$modal.modal('hide');
					$('.realname').text(response.name);
				} else {
					throw new Error(Translator.__('Unknow error occured. Please try again.'));
				}

				return;
			} catch (errorMessage) { showError(errorMessage); return;}
		}

		async function disconnect() {
			try { 
				var accessToken = await getAccessToken();
				if (!accessToken) {
					throw new Error(Translator.__('Access token is missing please try again.'));
				}
				
				startStatusModal();
				await startServerSideSessionAction(accessToken);
				await disconnectAccountAction(accessToken);

				$modal.modal('hide');
				$('#km_auth_user_facebook_preferences_display_connect_button').removeClass('hide');

				return;
			} catch (errorMessage) { showError(errorMessage); return;}
		}

		async function revoke() {
			try { 
				var accessToken = await getAccessToken();
				if (!accessToken) {
					throw new Error(Translator.__('Access token is missing please try again.'));
				}
				
				startStatusModal();
				await startServerSideSessionAction(accessToken);
				await disconnectAccountAction(accessToken);
				// await disconnectAccountAction(accessToken);
				let revoked = await api('/me/permissions', 'delete');
				if (revoked.success == true) {
					$modal.modal('hide');
					$('#km_auth_user_facebook_preferences_display_connect_button').removeClass('hide');
				} else {
					throw new Error(Translator.__('Unknow error occured. Please try again.'));
				}

				return;
			} catch (errorMessage) { showError(errorMessage); return;}
		}

// Zikula Actions
		async function connectLoginRedirectAction(accessToken, account) {
			try {
				await connectAccountAction(accessToken, account);
				await logInZikulaAction(accessToken, account);
				redirectAfterLogin();

				return true;				
			} catch (errorMessage) { showError(errorMessage); return;}
		}

		async function startServerSideSessionAction(accessToken) {
			$row = getStatusModalIconTextRow({ iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Loading user data...')});

			try {
				await startServerSideSession(accessToken);
				$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('User data loaded')}));

				return true;				
			} catch (err) {
				throw new Error(err.responseJSON.message);
			}
		}

		async function checkRegistrationStatusAction(accessToken) {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Looking for connected account...')});

			try {
				var isRegisteredCheck = await checkRegistrationStatus(accessToken);
				if (isRegisteredCheck.status == 'found') {
					$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Found connected account!')}));
					
					return isRegisteredCheck.account;
				}
				$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Connected account not found.')}));

				return false;				
			} catch (err) { throw new Error(err.responseJSON.message);}
		}

		async function connectAccountAction(accessToken, account) {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Connecting...')});

			try {
				await connectAccount(accessToken, account);
				$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Connected.')}));

				return true;				
			} catch (err) { throw new Error(err.responseJSON.message);}
		}

		async function disconnectAccountAction(accessToken) {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Disconnecting...')});

			try {
				await disconnectAccount(accessToken);
				$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Disconnected.')}));

				return true;				
			} catch (err) { throw new Error(err.responseJSON.message);}
		}

		async function checkEmailStatusAction(accessToken) {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Checking email...')});

			try {
				var emailCheck = await checkEmailStatus(accessToken);
				if (emailCheck.status == 'missing') {

					throw new Error(Translator.__('Missing email address!'));

				} else if (emailCheck.status == 'prohibited') {

					throw new Error(Translator.__('Email is progibited!.'));

				} else if (emailCheck.status == 'present-registered') {
					let emailRegisteredTxt  = Translator.__('Your email is already registered!');
					$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: emailRegisteredTxt}));

					return emailCheck.accounts;
				} else if (emailCheck.status == 'present-unregistered') {
					$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Email is unregistered!.')}));

					return false;
				} else {
					throw new Error(Translator.__('Unknow error occured please try again.'));
				}
			} catch (err) { throw new Error(err.responseJSON.message);}
		}

		async function logInZikulaAction(accessToken, account) {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Logging in...')});

			try {
				await logIn(accessToken, account);
				$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Logged in.')}));

				return true;				
			} catch (err) {
				throw new Error(err.responseJSON.message);
			}
		}

		async function accountChooserAction(accessToken, accounts) {
			// account choser 
			let accountsFoundTxt  = accounts.length + Translator.__(' accounts found!') + ' ' + Translator.__('Select account to connect');
			let foundTxt  = (accounts.length == 1) ? Translator.__('One account found!') : accountsFoundTxt;

			$row = getStatusModalIconTextRow({iconClass:'fa fa-check text-success', text: foundTxt});
			$accountChooser = getAccountChooser(accounts);
			$accountChooser.on('click', "[data-accountid]", function(e) {
				e.preventDefault();
				let account = $(this).data('accountid');
				connectLoginRedirectAction(accessToken, account);
			});

			$row.append(getDiv(false, 'col-xs-12', $accountChooser));
		}

		async function registerZikulaAction(accessToken) {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Registering...')});

			try {
				let response = await registerAccount(accessToken);
				$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Account registered.')}));

				return response.account;				
			} catch (err) {
				throw new Error(err.responseJSON.message);
			}
		}

		// async function preferencesAction(accessToken) {
		// 	$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Loading data...')});

		// 	try {
		// 		let response = await getFacebookUserAccount(accessToken);
		// 		$row.html(getIconTextCol({iconClass:'fa fa-check text-success', text: Translator.__('Data loaded!')}));
		// 		$modal.modal('hide');
		// 		$('#km_auth_user_facebook_preferences_display_connect_button').addClass('hide');

		// 		return true;				
		// 	} catch (err) {
		// 		throw new Error(err.responseJSON.message);
		// 	}
		// }

		function redirectAfterLogin() {
			$row = getStatusModalIconTextRow({iconClass:'fa fa-circle-o-notch fa-spin', text: Translator.__('Redirecting...')});

			var currentPathName = window.location.pathname;
			var redirectHomePaths = getRedirectToHomePathsArray();
			if (redirectHomePaths.includes(currentPathName)) {
				window.location.href = '/';
			} else {
				window.location.href = currentPathName;
			}
		}

// Zikula Calls
		// this call starts server side session
		function startServerSideSession(accessToken) {
			return new Promise(async (resolve, reject) => {				
				$.post(Routing.generate('kaikmediaauthmodule_facebook_start'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}
		// checks if the email associated with access token is registered
		// and if so how many accounts use this email
		function checkRegistrationStatus(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_iszikulauser'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}

		function checkEmailStatus(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_checkemail'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}

		function connectAccount(accessToken, account) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_connectaccount'), { accessToken: accessToken, account: account})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}

		function disconnectAccount(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_disconnectaccount'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}

		function updateNameAction(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_updatename'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}

		function updateAvatarAction(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_updateavatar'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}
// login 
		function logIn(accessToken, account) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_login'), { accessToken: accessToken, account: account})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}
// register
		function registerAccount(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_register'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}
// account
		function getFacebookUserAccount(accessToken) {
			return new Promise(async (resolve, reject) => {
				$.post(Routing.generate('kaikmediaauthmodule_facebook_getaccount'), { accessToken: accessToken})
					.done( (response) => resolve(response) )
					.fail( (error) => reject(error));
			});
		}

// Display
		// create modal
		function startStatusModal() {
			createModal({
				title: Translator.__('Registration'),
				showHeader: true,
				showFooter: false
			});
		}

		function statusModalChangeBody(content = '', mode = 'replace') {
			if (!$modal) {
				return false;
			}
			var $body = $modal.find('.modal-body');
			switch (mode) {
				case 'replace':
					$body.html(content);
					break;
				case 'append':
					$body.append(content);
					break;
				case 'prepend':
					$body.prepend(content);
					break;
			}

			return true;
		}

		function getStatusModalIconTextRow(options) {
			var options = $.extend({
				mode: 'append',
				rowId : false,
				rowClass : 'row',
				colId : false,
				colClass: 'col-xs-12',
				iconId: false,
				iconClass : 'fa fa-circle-o-notch',
				text : '',
			}, options);

			let $row = getIconTextRow(options);
			statusModalChangeBody($row, 'append');

			return $row;
		}

		function getIconTextRow(options) {
			var options = $.extend({
				rowId : false,
				rowClass : 'row',
				colId : false,
				colClass: 'col-xs-12',
				iconId: false,
				iconClass : 'fa fa-circle-o-notch',
				text : '',
			}, options);

			return getDiv(options.rowId, options.rowClass, getIconTextCol(options));
		}

		function getIconTextCol(options) {
			var options = $.extend({
				colId : false,
				colClass: 'col-xs-12',
				iconId: false,
				iconClass : 'fa fa-circle-o-notch',
				text : '',
			}, options);

			return getDiv(options.colId, options.colClass, getIconText(options));
		}

		function getIconText(options) {
			var options = $.extend({
				iconId: false,
				iconClass : 'fa fa-circle-o-notch',
				text : '',
			}, options);

			return getIcon(options.iconId, options.iconClass)[0].outerHTML + ' ' + options.text;
		}

		function getIcon(id = false, css = false) {
			var $icon = $('<i></i>');
			if (id) {$icon.id(id);}
			if (css) {$icon.addClass(css);}

			return $icon;
		}

		function getDiv(id = false, css = false, content = false) {
			var $row = $('<div></div>');
			if (id) { $row.id(id);}
			if (css) { $row.addClass(css);}
			if (content) { $row.html(content);}

			return $row;
		}

		function showError(message) {
			let errorTxt = message ? message : Translator.__('Unknow error please try again :(');

			let icon = getIcon(false, 'fa fa-close')[0].outerHTML;
			$alert = getDiv(false, 'alert alert-danger', icon + ' ' + errorTxt);

			statusModalChangeBody(getDiv(false, false, $alert), 'replace');

			return;
		}

		function areMultipleAccountsAllowed() {
			let multipleAccountsAllowed = KaikMedia.Auth.Config.Global.getVar('multipleSameAccountsAllowed', false);
			if (!multipleAccountsAllowed || multipleAccountsAllowed == '0') {
				return false;
			} else {
				return true;
			}
		}

		function getRedirectToHomePathsArray() {
			let paths = KaikMedia.Auth.Config.FB.getVar('redirectHomePaths', '');

			return paths.split(',');
		}

		function loadButtons() {
			$buttons = $("a:contains('kaikmedia_auth_facebook_button_')");
			$buttons.each((index, element) => {
				$(element).hide(); // hide it... kind of helps...
				$(element).replaceWith(generateButton(decodeButtonData($(element).text())));
			})
		};

		function decodeButtonData(string) {
			let data = string.substring('kaikmedia_auth_facebook_button_'.length).split("-")
			var options = {
				size : data[0],
				button: data[1],
				layout: data[2],
				auto_logout_link: data[3] == 'yes' ? true : false,
				use_continue_as: data[4] == 'yes' ? true : false,
			};

			return options;
		};

		function generateButton(options) {
			$button = $('<div class="fb-login-button"></div>');
			$button.attr('data-size', options.size);
			$button.attr('data-button-type', options.button);
			$button.attr('data-layout', options.layout);
			$button.attr('auto_logout_link', options.auto_logout_link);       
			$button.attr('data-use-continue-as', options.use_continue_as);       
			$button.attr('scope', "public_profile,email");
			$button.attr('onlogin', "KaikMedia.Auth.facebook.logInRegister();");

			return $button;
		};

// Zikula Accounts Chooser
		function getAccountChooser(accounts) {
			if (!accounts) {
				return;
			}
			// var $chooser = $('<ul class="list-unstyled"></ul>');
			var $chooser = $('<div class=""></div>');
			for (i = 0; i < accounts.length; i++) { 
				$chooser.append(getAccountPreview(accounts[i]));
			}

			if (areMultipleAccountsAllowed()) {
				// @todo add new account info
			}

			return $chooser;
		}

		function getAccountPreview(account) {
			if (!account) {
				return;
			}

			var $anchor = $('<a href="#" class="btn btn-default text-center" data-accountid="' + account.uid + '" role="button"></a>');
			var $uname = $('<span class="uname">' + account.uname +  '</span>');
			var $avatarRow = $('<div class="avatar"></div>').append(account.avatar);

			$anchor.append($avatarRow);
			$anchor.append($uname);

			return $anchor;
		}

// Facebook
		function getLoginStatus() {
			return new Promise(async (resolve) => {
				FB.getLoginStatus((response) => {
					resolve(response);
				});
			});
		}

		function login(params = { scope: 'public_profile,email' }) {
			return new Promise(async (resolve) => {
				FB.login((response) => {
					resolve(response);
				}, params);
			});
		}

		function api(...params) {
			return new Promise(async (resolve) => {
	
				const callback = (response) => {
					resolve(response);
				};
	
				if (params.length > 3) {
					params = params.slice(0, 3);
				}
	
				params.push(callback);
	
				FB.api(...params);
			});
		}

// Modal
		function createModal(options) {
			var options = $.extend({
					title : '',
					body : '',
					remote : false,
					showHeader: true,
					showFooter: true,
					backdrop : 'static',
					size : false,
					onShow : false,
					onHide : false,
					actions : false
				}, options);
		
			var onShow = typeof options.onShow == 'function' ? options.onShow : function () {};
			var onHide = typeof options.onHide == 'function' ? options.onHide : function () {};
			var modalId = 'oauth-facebook';
	
			$modal = $('<div id="'+ modalId +'" class="modal fade"><div class="modal-dialog"><div class="modal-content"></div></div></div>')
						// .appendTo('body')
						;
	
			$modal.on('shown.bs.modal', function (e) {
				onShow.call(this, e);
			});
			$modal.on('hidden.bs.modal', function (e) {
				onHide.call(this, e);
			});
	
			var modalClass = {
				small : "modal-sm",
				large : "modal-lg"
			};
		
			$modal.data('bs.modal', false);
			$modal.find('.modal-dialog').removeClass().addClass('modal-dialog ' + (modalClass[options.size] || ''));
			
			if (options.showHeader) {
				$modal_header = $('<div class="modal-header"></div>');
				$modal_top_close = $('<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>');
				$modal_title = $('<h4 class="modal-title">${title}</h4>'.replace('${title}', options.title));
				$modal_header
					.append($modal_top_close)
					.append($modal_title)
					;
				$modal.find('.modal-content').append($modal_header);
			}
	
			$modal_body = $('<div class="modal-body">${body}</div>'.replace('${body}', options.body));
			$modal.find('.modal-content').append($modal_body);
	
			if (options.showFooter) {
				$modal_footer = $('<div class="modal-footer"></div>');
				$modal.find('.modal-content').append($modal_footer);
	
				var footer = $modal.find('.modal-footer');
				if (Object.prototype.toString.call(options.actions) == "[object Array]") {
					for (var i = 0, l = options.actions.length; i < l; i++) {
						options.actions[i].onClick = typeof options.actions[i].onClick == 'function' ? options.actions[i].onClick : function () {};
						$('<button type="button" class="btn ' + (options.actions[i].cssClass || '') + '">' + (options.actions[i].label || '{Label Missing!}') + '</button>').appendTo(footer).on('click', options.actions[i].onClick);
					}
				} else {
					$('<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>').appendTo(footer);
				}
			}
	
			$modal.modal(options);
		}

		function getSDKLanguage() {

			let langMap = {
				'en': 'en_US',
				'pl': 'pl_PL',
				'de': 'de_DE',
			};

			return langMap[Zikula.Config.lang] !== 'undefined' ? langMap[Zikula.Config.lang] : 'en_US' ;
		}
		function getScript() {
			return new Promise((resolve) => {
				if (window.FB) {
					resolve(window.FB);
				}
	
				const id = 'facebook-jssdk';
				const fjs = document.querySelectorAll('script')[0];
				if (document.getElementById(id)) {
					return;
				}
				const locale = getSDKLanguage();
				const js = document.createElement('script');
				js.id = id;
				js.src = '//connect.facebook.net/'+ locale +'/sdk.js';
	
				js.addEventListener('load', () => {
					Object.assign(this, {
						AppEvents: window.FB.AppEvents,
						Canvas: window.FB.Canvas,
						Event: window.FB.Event,
						Frictionless: window.FB.Frictionless,
						XFBML: window.FB.XFBML,
					});

					resolve(window.FB);
				});
	
				fjs.parentNode.insertBefore(js, fjs);
			});
		}

        return {
			init: init,
			logInRegister: logInRegister,
			getScript: getScript,
			updateAvatar: updateAvatar,
			updateName: updateName,
			disconnect: disconnect,
			revoke: revoke
		};
	})();

	$(document).ready(async function() {
		const FB = await KaikMedia.Auth.facebook.init();
	});
})(jQuery, Routing, Translator, Zikula);

