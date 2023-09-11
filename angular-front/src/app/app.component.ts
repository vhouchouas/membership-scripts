import { Component } from '@angular/core';
import { DefaultService } from './generated/api/api/default.service';
import { DefaultLoginService } from './generated/login/api/default.service';
import { LoginPostRequest} from './generated/login/model/loginPostRequest';
import { User } from './generated/login/model/user';
import { ApiMembersSortedByLastRegistrationDateGet200ResponseInner } from './generated/api/model/apiMembersSortedByLastRegistrationDateGet200ResponseInner';
import { Observable } from 'rxjs';

@Component({
	selector: 'app-root',
	templateUrl: './app.component.html',
	styleUrls: ['./app.component.css']
})
export class AppComponent {
	loggedIn = false; // TODO: also get the name somehow?
	logoutInProgress = false;
	page = "members";
	members: Array<ApiMembersSortedByLastRegistrationDateGet200ResponseInner> | null = null;


	constructor(
		private apiClient: DefaultService,
		private loginClient: DefaultLoginService,
	) {}

	loginInitializedEventReceived() {
		this.loggedIn = true;
		this.fetchMembers();
	}

	setPage(page: string): void {
		this.page = page;
	}

	fetchMembers() {
		let obs: Observable<Array<ApiMembersSortedByLastRegistrationDateGet200ResponseInner>> = this.apiClient.apiMembersSortedByLastRegistrationDateGet();
		let self = this;
		obs.subscribe({
			next(members) {
				console.log("got " + members.length + " members");
				self.members = members.reverse();
			},
			error(err) {
				console.log("failed to get members: " + JSON.stringify(err));
			}
		});

	}

	logout() {
		this.logoutInProgress = true;
		let obs: Observable<any> = this.loginClient.logoutPost();
		let self = this;
		obs.subscribe({
			next() {
				console.log("logout successful");
				self.loggedIn = false;
				self.logoutInProgress = false;
			},
			error(err) {
				self.logoutInProgress = false;
				console.log("Failed to logout: " + JSON.stringify(err));
			}
		});
	}

	passwordChangedSuccessfullyEventReceived() {
		this.page = "members";
	}
}
