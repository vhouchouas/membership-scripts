import { Component, Output, EventEmitter } from '@angular/core';
import { FormBuilder } from '@angular/forms';
import { DefaultLoginService } from '../generated/login/api/default.service';
import { LoginPostRequest} from '../generated/login/model/loginPostRequest';
import { User } from '../generated/login/model/user';
import { Observable } from 'rxjs';

@Component({
	selector: 'app-login',
	templateUrl: './login.component.html',
	styleUrls: ['./login.component.css']
})
export class LoginComponent {
	@Output() loginSuccessful = new EventEmitter();
	credentialsBeingProcessed = false;

	credentialsForm = this.formBuilder.group({
		username: '',
		password: ''
	});

	constructor(
		private loginClient: DefaultLoginService,
		private formBuilder: FormBuilder
	) {
		this.isLoggedIn();
	}

	isLoggedIn() {
		let obs: Observable<User> = this.loginClient.loginGet();
		let self = this;
		obs.subscribe({
			next(user) {
				console.log("already logged in as " + user.login);
				self.loginSuccessful.emit(); // TODO: shall we emit username?
			},
			error(err) {
				console.log("Not logged in yet: " + JSON.stringify(err));
			}
		});
	}

	onSubmit(): void {
		let formValues = this.credentialsForm.value;
		let payload: LoginPostRequest = {
			username: formValues.username ?? '',
			password: formValues.password ?? ''
		};

		let self = this;
		this.credentialsBeingProcessed = true;
		let obs: Observable<User> = this.loginClient.loginPost(payload);
		obs.subscribe({
			next(user) {
				console.log("successfully logged in as " + user.login);
				self.loginSuccessful.emit(); // TODO: shall we emit username?
			},
			error(err) {
				self.credentialsBeingProcessed = false;
				console.log("Failed to log in: " + JSON.stringify(err));
				window.alert("Authentication failed");
			}
		});
	}
}
