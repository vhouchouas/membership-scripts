import { Component, Output, EventEmitter } from '@angular/core';
import { FormBuilder } from '@angular/forms';
import { Observable } from 'rxjs';
import { DefaultService } from '../generated/api/api/default.service';
import { ApiUpdateUserPasswordPostRequest } from '../generated/api/model/apiUpdateUserPasswordPostRequest';

@Component({
	selector: 'app-password-changer',
	templateUrl: './password-changer.component.html',
	styleUrls: ['./password-changer.component.css']
})
export class PasswordChangerComponent {
	@Output() passwordChangedSuccessfully = new EventEmitter();
	newPasswordSubmitted = false;

	newPasswordForm = this.formBuilder.group({
		newPassword: ''
	});

	constructor(
		private apiClient: DefaultService,
		private formBuilder: FormBuilder
	) {}

	onSubmit(): void {
		let formValues = this.newPasswordForm.value;
		let payload: ApiUpdateUserPasswordPostRequest = {
			newPassword: formValues.newPassword ?? '',
		};

		let self = this;
		this.newPasswordSubmitted = true;
		let obs: Observable<any> = this.apiClient.apiUpdateUserPasswordPost(payload);
		obs.subscribe({
			next() {
				console.log("password successfully updated");
				self.passwordChangedSuccessfully.emit();
			},
			error(err) {
				self.newPasswordSubmitted = false;
				let errorMsg = "Failed to update password: " + JSON.stringify(err);
				console.log(errorMsg);
				window.alert(errorMsg);
			}
		});

	}
}
