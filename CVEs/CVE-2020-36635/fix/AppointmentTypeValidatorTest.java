package org.openmrs.module.appointmentscheduling.validator;

import org.apache.commons.lang.StringUtils;
import org.junit.Before;
import org.junit.Test;
import org.mockito.Mockito;
import org.openmrs.module.appointmentscheduling.AppointmentType;
import org.openmrs.module.appointmentscheduling.api.AppointmentService;
import org.springframework.validation.Errors;

import static org.mockito.Mockito.never;
import static org.mockito.Mockito.when;
import static org.powermock.api.mockito.PowerMockito.mock;

public class AppointmentTypeValidatorTest {
	
	AppointmentService appointmentService;
	
	private AppointmentTypeValidator appointmentTypeValidator;
	
	private AppointmentType appointmentType;
	
	private Errors errors;
	
	@Before
	public void setUp() throws Exception {
		appointmentService = mock(AppointmentService.class);
		appointmentType = new AppointmentType("name", "desciption", 10);
		appointmentTypeValidator = new AppointmentTypeValidator();
		appointmentTypeValidator.setAppointmentService(appointmentService);
		errors = mock(Errors.class);
		
	}
	
	@Test
	public void mustRejectAppointmentTypeWithDuplicatedName() throws Exception {
		when(appointmentService.verifyDuplicatedAppointmentTypeName(appointmentType)).thenReturn(true);
		
		appointmentTypeValidator.validate(appointmentType, errors);
		
		Mockito.verify(errors).rejectValue("name", "appointmentscheduling.AppointmentType.nameDuplicated");
	}
	
	@Test
	public void mustAcceptAppointmentTypeWithNotDuplicatedName() throws Exception {
		when(appointmentService.verifyDuplicatedAppointmentTypeName(appointmentType)).thenReturn(false);
		
		appointmentTypeValidator.validate(appointmentType, errors);
		
		Mockito.verify(errors, never()).rejectValue("name", "appointmentscheduling.AppointmentType.nameDuplicated");
	}
	
	@Test
	public void mustRejectAppointmentTypeWithNegativeDuration() throws Exception {
		AppointmentType appointmentType = new AppointmentType("name", "desciption", -5);
		
		appointmentTypeValidator.validate(appointmentType, errors);
		
		Mockito.verify(errors).rejectValue("duration", "appointmentscheduling.AppointmentType.duration.errorMessage");
	}
	
	@Test
	public void mustRejectAppointmentTypeWithDurationEqualsZero() throws Exception {
		AppointmentType appointmentType = new AppointmentType("name", "desciption", 0);
		
		appointmentTypeValidator.validate(appointmentType, errors);
		
		Mockito.verify(errors).rejectValue("duration", "appointmentscheduling.AppointmentType.duration.errorMessage");
	}
	
	@Test
	public void mustAcceptAppointmentTypeWithPositiveDuration() throws Exception {
		appointmentTypeValidator.validate(appointmentType, errors);
		
		Mockito.verify(errors, never()).rejectValue("duration",
		    "appointmentscheduling.AppointmentType.duration.errorMessage");
	}
	
	@Test
	public void mustRejectAppointmentTypeWithNullValue() throws Exception {
		AppointmentType appointmentType = new AppointmentType();
		
		appointmentTypeValidator.validate(appointmentType, errors);
		
		Mockito.verify(errors).rejectValue("duration", "appointmentscheduling.AppointmentType.duration.errorMessage");
	}
	
	@Test
	public void mustRejectAppointmentTypeNameWithMoreThan100Characters() throws Exception {
		String longName = StringUtils.repeat("*", 101);
		AppointmentType appointmentTypeLongName = new AppointmentType(longName, "", 10);
		
		appointmentTypeValidator.validate(appointmentTypeLongName, errors);
		
		Mockito.verify(errors).rejectValue("name", "appointmentscheduling.AppointmentType.longName.errorMessage");
	}

	@Test
	public void mustRejectAppointmentTypeNameWithXSS() throws Exception {
		String evilName = "<script>alert(1)</script>";
		AppointmentType appointmentTypeEvilName = new AppointmentType(evilName, "", 10);

		appointmentTypeValidator.validate(appointmentTypeEvilName, errors);

		Mockito.verify(errors).rejectValue("name", "appointmentscheduling.AppointmentType.unsafeName.errorMessage");
	}

	@Test
	public void mustRejectAppointmentTypeDescriptionWithMoreThan1024Characters() throws Exception {
		String longDescription = StringUtils.repeat("*", 1025);
		AppointmentType appointmentTypeLongDuration = new AppointmentType("long description", longDescription, 10);
		
		appointmentTypeValidator.validate(appointmentTypeLongDuration, errors);
		
		Mockito.verify(errors).rejectValue("description", "appointmentscheduling.AppointmentType.description.errorMessage");
	}
	
}
