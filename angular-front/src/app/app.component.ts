import { Component, OnInit } from '@angular/core';
import { DefaultService } from './generated/api/api/default.service';
import { DefaultLoginService } from './generated/login/api/default.service';
import { LoginPostRequest} from './generated/login/model/loginPostRequest';
import { User } from './generated/login/model/user';
import { Observable } from 'rxjs';

@Component({
	selector: 'app-root',
	templateUrl: './app.component.html',
	styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {
	constructor(
		private apiClient: DefaultService,
		private loginClient: DefaultLoginService,
	) {}

	ngOnInit() {
		this.login();
	}

	login(): void {
		let payload: LoginPostRequest = {
			username: "guillaume",
			password: "tutu"
		};
		let obs: Observable<User> = this.loginClient.loginPost(payload);
		obs.subscribe({
			next(user) {
				console.log("succesfully logged in as " + user.login);
			},
			error(err) {
				console.log("Failed to log in: " + JSON.stringify(err));
			}
		});
	}
}
