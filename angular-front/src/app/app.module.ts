import { NgModule } from '@angular/core';
import { ApiModule } from  './generated/api/api.module';
import { LoginApiModule } from './generated/login/api.module';
import { Configuration, ConfigurationParameters } from './generated/api/configuration';
import { Configuration as LoginConfiguration, ConfigurationParameters as LoginConfigurationParamters} from './generated/login/configuration';
import { BrowserModule } from '@angular/platform-browser';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';

import { AppComponent } from './app.component';
import { LoginComponent } from './login/login.component';
import { PasswordChangerComponent } from './password-changer/password-changer.component';

export function clientConfigFactory(): Configuration {
	return new Configuration(buildClientsConfigParameters());
}
export function loginClientConfigFactory(): LoginConfiguration {
	return new LoginConfiguration(buildClientsConfigParameters());
}
function buildClientsConfigParameters() {
	let host = window.location.host;
	let protocol = window.location.protocol;
	return {
		basePath: protocol + "//" + host
	}
}

@NgModule({
	declarations: [
		AppComponent,
		LoginComponent,
		PasswordChangerComponent
	],
	imports: [
		BrowserModule,
		HttpClientModule,
		FormsModule,
		ReactiveFormsModule,
		ApiModule.forRoot(clientConfigFactory),
		LoginApiModule.forRoot(loginClientConfigFactory),
	],
	providers: [],
	bootstrap: [AppComponent]
})
export class AppModule { }
