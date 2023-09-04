import { Component } from '@angular/core';
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
export class AppComponent {
	loginInitialized = false; // TODO: also get the name somehow?

	constructor(
		private apiClient: DefaultService,
	) {}

	loginInitializedEventReceived() {
		this.loginInitialized = true;
	}
}
