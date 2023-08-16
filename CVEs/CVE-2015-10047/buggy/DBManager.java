import utils.AccountType;
import utils.Pair;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.ArrayList;

public class DBManager {
        Connection c;
        Statement stmt;

        public DBManager() {
                try {
                        Class.forName("org.postgresql.Driver");
                        c = DriverManager
                                .getConnection("jdbc:postgresql://localhost:5432/School register",
                                        "postgres", "kamil");
//                        System.out.println("Opened database successfully");
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }


        public ArrayList<String> getStudentGrades(String studentID, int subjectID) {
                ArrayList<String> grades = new ArrayList<String>();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT wartosc,tematyka,nazwa from oceny_uczniow join rodzaje_aktywnosci ra on id_aktywnosci=ra.id where id_ucznia='" + studentID + "' and id_przedmiotu=" + subjectID + ";");
                        while (rs.next()) {
                                int value = rs.getInt("wartosc");
                                String topic = rs.getString("tematyka");
                                String name = rs.getString("nazwa");
//                                System.out.println(topic + " " + name + " " + value);
                                grades.add(topic + " " + name + " " + value);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return grades;
        }

        public ArrayList<String> getStudentAbsences(String studentID, String fromData, String toData) {
                ArrayList<String> absences = new ArrayList<String>();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT pl.data,p.nr_lekcji from nieobecnosci n join przeprowadzone_lekcje pl on n.id_lekcji=pl.id join plan_lekcji p on pl.id_lekcji=p.id where id_ucznia='" + studentID + "' and data>=to_date('" + fromData + "', 'DD.MM.YYYY') and data<=to_date('" + toData + "', 'DD.MM.YYYY');");
                        while (rs.next()) {
                                String date = rs.getDate("data").toString();
                                int lesson = rs.getInt("nr_lekcji");
//                                System.out.println(date + " lekcja nr: " + lesson);
                                absences.add(date + " lekcja nr: " + lesson);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return absences;
        }

        public ArrayList<String> getStudentNotes(String studentID, String fromData, String toData) {
                ArrayList<String> notes = new ArrayList<String>();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT opis,data_wystawienia,czy_pozytywna from uwagi where id_ucznia='" + studentID + "' and data_wystawienia>=to_date('" + fromData + "', 'DD.MM.YYYY')" + " and data_wystawienia<=to_date('" + toData + "', 'DD.MM.YYYY');");
                        while (rs.next()) {
                                String description = rs.getString("opis");
                                String date = rs.getDate("data_wystawienia").toString();
                                boolean positive = rs.getBoolean("czy_pozytywna");
//                                System.out.println(date + " " + positive + ": " + description);
                                notes.add(date + " " + positive + ": " + description);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return notes;
        }

        public ArrayList<Pair<Integer,String> > getStudentSubjects(String studentID) {
                ArrayList<Pair<Integer,String> > subjects = new ArrayList<Pair<Integer,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT p.id,nazwa from przedmioty p join klasy k on p.id_klasy=k.id join uczniowie u on u.id_klasy=k.id where pesel='" + studentID + "';");
                        while (rs.next()) {
                                Pair<Integer,String> pair = new Pair<Integer, String>(rs.getInt("id"),rs.getString("nazwa"));

//                                System.out.println(pair.getX()+" "+pair.getY());
                                subjects.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return subjects;
        }

        public ArrayList<Pair<Integer,String> > getTeacherSubjects(int teacherID) {
                ArrayList<Pair<Integer,String> > subjects = new ArrayList<Pair<Integer,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT p.id,nazwa,oddzial,rok_rozpoczecia from przedmioty p join klasy k on p.id_klasy=k.id where aktywny=true and id_prowadzacego='" + teacherID + "';");
                        while (rs.next()) {
                                Pair<Integer,String> pair = new Pair<Integer, String>(rs.getInt("id"),rs.getString("nazwa") + " klasa: " + rs.getString("oddzial") + " " + rs.getInt("rok_rozpoczecia"));
                                String name = rs.getString("nazwa");
                                String section = rs.getString("oddzial");
                                int startYear = rs.getInt("rok_rozpoczecia");
//                                System.out.println(name + " klasa " + startYear + section);
                                subjects.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return subjects;
        }

        public ArrayList<Pair<String,String> > getSubjectStudents(int subjectID) {
                ArrayList<Pair<String,String> > students = new ArrayList<Pair<String,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,pesel from przedmioty p join klasy k on p.id_klasy=k.id join uczniowie u on u.id_klasy=k.id where p.id='" + subjectID + "';");
                        while (rs.next()) {
                                String name = rs.getString("imie");
                                String lastname = rs.getString("nazwisko");
                                String pesel = rs.getString("pesel");
                                Pair<String,String> pair = new Pair<String, String>(rs.getString("pesel"),rs.getString("imie")+ " " + rs.getString("nazwisko"));
//                                System.out.println(name + " " + lastname + " " + pesel);
                                students.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return students;
        }

        public void addStudentGrade(int subjectID, String studentID, int gradeValue, int activityID, String topic) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO oceny_uczniow(id_przedmiotu,id_ucznia,wartosc,id_aktywnosci,tematyka) values(" + subjectID + "," + studentID + "," + gradeValue + "," + activityID + ",'" + topic + "');");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addStudentNote(String studentID, int teacherID, String note, boolean isPositive, String date) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO uwagi(id_ucznia,id_nauczyciela,opis,czy_pozytywna,data_wystawienia) values('" + studentID + "'," + teacherID + ",'" + note + "'," + isPositive + ",to_date('" + date + "', 'DD.MM.YYYY'));");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addStudentAbsence(String studentID, int lessonID) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO nieobecnosci(id_ucznia,id_lekcji) values('" + studentID + "'," + lessonID + ");");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public int addCompletedLesson(String data, int teacherID, int lessonID, String topic) {
                int i=-1;
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO przeprowadzone_lekcje(data,id_prowadzacego,id_lekcji,temat_zajec) values(to_date('" + data + "', 'DD.MM.YYYY')," + teacherID + "," + lessonID + ",'" + topic + "');");
//                        System.out.println("success");
                        ResultSet rs = stmt.executeQuery("select lastval();");
                        rs.next();
                        i=rs.getInt(1);
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return i;
        }

        public void addStudent(String name, String lastname, String pesel, int phoneNumber, int classID) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO uczniowie(imie,nazwisko,pesel,telefon_do_rodzica,id_klasy) values('" + name + "','" + lastname + "','" + pesel + "'," + phoneNumber + "," + classID + ");");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addclass(String section, int startYear, int tutorID) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO klasy(oddzial,rok_rozpoczecia,id_wychowawcy) values('" + section + "'," + startYear + "," + tutorID + ");");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addTeacher(String name, String lastname) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO nauczyciele(imie,nazwisko) values('" + name + "','" + lastname + "');");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addSubject(String name, int classID, int teacherID) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO przedmioty(nazwa,id_klasy,id_prowadzacego) values('" + name + "'," + classID + "," + teacherID + ");");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addScheduleLesson(int subjectID, int lessonNumber, int weekday) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO plan_lekcji(id_przedmiotu,nr_lekcji,dzien_tygodnia) values(" + subjectID + "," + lessonNumber + "," + weekday + ");");
//                        System.out.println("success");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void yearEnd() {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("UPDATE przedmioty SET aktywny = FALSE;");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void deactivateStudent(String studentID) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("UPDATE uczniowie SET aktywny = false where pesel='" + studentID + "';");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addStudentUser(String login, String password, String pesel) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO uzytkownicy(login,haslo) values('" + login + "','" + password + "');");
                        stmt.executeUpdate("UPDATE uczniowie SET id_uzytkownika = '" + login + "' where pesel='" + pesel + "';");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public void addTeacherUser(String login, String password, int teacherID) {
                try {
                        stmt = c.createStatement();
                        stmt.executeUpdate("INSERT INTO uzytkownicy(login,haslo) values('" + login + "','" + password + "');");
                        stmt.executeUpdate("UPDATE nauczyciele SET id_uzytkownika = '" + login + "' where id=" + teacherID + ";");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public User signIn(String login, String password) {
                //w parze pierwszy argument to string drugi boolean
                //i true jesli to uczen, false jesli to nauczyciel
                //null jesli login nie istnieje lub zle haslo
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT * from uzytkownicy join uczniowie on login=id_uzytkownika where login='" + login + "';");
                        while (rs.next()) {
                                String pesel = rs.getString("pesel");
                                String pass= rs.getString("haslo");
                                if (!password.equals(pass)) return null;
                                return new User(pesel, AccountType.STUDENT,pass);
                        }
                        rs = stmt.executeQuery("SELECT * from uzytkownicy join nauczyciele on login=id_uzytkownika where login='" + login + "';");
                        while (rs.next()) {
                                int id = rs.getInt("id");
                                String pass= rs.getString("haslo");
                                if (!password.equals(pass)) return null;
                                return new User(Integer.toString(id), AccountType.TEACHER,pass);
                        }
                        return null;
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return null;
        }

        public ArrayList<ArrayList<String> > getLessonShedule(String pesel){
                ArrayList<ArrayList<String> > shedule = new ArrayList<ArrayList<String>>();
                for(int i=0;i<5;i++){
                        shedule.add(new ArrayList<String>());
                }
                try {
                        for(int i=2;i<=6;i++){
                                stmt = c.createStatement();
                                ResultSet rs = stmt.executeQuery("select p.nazwa,pl.nr_lekcji from plan_lekcji pl join przedmioty p on pl.id_przedmiotu = p.id join klasy k on p.id_klasy = k.id join uczniowie u on k.id=u.id_klasy where dzien_tygodnia = "+i+" and u.pesel = '"+pesel+"' order by pl.nr_lekcji;");
                                int j=0;
                                while (rs.next()) {
                                        int tmp = rs.getInt("nr_lekcji");
                                        while(j<tmp){ shedule.get(i-2).add(""); j++;}
                                        shedule.get(i-2).add(rs.getString("nazwa"));
                                        j++;
                                }
                                while(shedule.get(i-2).size()<10) shedule.get(i-2).add("");
                                rs.close();
                                stmt.close();
                        }
//                        System.out.println("success");
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return shedule;
        }
        public ArrayList<Pair<String,String> > getStudentsWithoutUser() {
                ArrayList<Pair<String,String> > students = new ArrayList<Pair<String,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,pesel from uczniowie where id_uzytkownika is null;");
                        while (rs.next()) {
                                Pair<String,String> pair = new Pair<String, String>(rs.getString("pesel"),rs.getString("imie")+" "+rs.getString("nazwisko"));
//                                System.out.println(pair.getX()+" "+pair.getY());
                                students.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return students;
        }
        public ArrayList<Pair<Integer,String> > getTeachersWithoutUser() {
                ArrayList<Pair<Integer,String> > teachers = new ArrayList<Pair<Integer,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,id from nauczyciele where id_uzytkownika is null;");
                        while (rs.next()) {
                                Pair<Integer,String> pair = new Pair<Integer, String>(rs.getInt("id"),rs.getString("imie")+" "+rs.getString("nazwisko"));
//                                System.out.println(pair.getX()+" "+pair.getY());
                                teachers.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return teachers;
        }
        public void changeStudentPassword(String pesel,String password){
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("select id_uzytkownika from uzytkownicy uz join uczniowie on id_uzytkownika=uz.login where pesel='" + pesel + "';");
                        rs.next();
                        String userID = rs.getString("id_uzytkownika");
                        stmt.close();
                        stmt = c.createStatement();
                        stmt.executeUpdate("UPDATE uzytkownicy SET haslo = '"+password+"' where login='"+userID+"';");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }
        public void changeTeacherPassword(int id,String password){
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("select id_uzytkownika from uzytkownicy uz join nauczyciele on id_uzytkownika=uz.login where id=" + id + ";");
                        rs.next();
                        String userID = rs.getString("id_uzytkownika");
                        stmt.close();
                        stmt = c.createStatement();
                        stmt.executeUpdate("UPDATE uzytkownicy SET haslo = '"+password+"' where login = '"+userID+"';");
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
        }

        public ArrayList<Pair<Integer,String> > getActivities(){
                ArrayList<Pair<Integer,String> > activities = new ArrayList<Pair<Integer,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT id,nazwa from rodzaje_aktywnosci;");
                        while (rs.next()) {
                                Pair<Integer,String> pair = new Pair<Integer, String>(rs.getInt("id"),rs.getString("nazwa"));
//                                System.out.println(pair.getX()+" "+pair.getY());
                                activities.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return activities;
        }

        public ArrayList<Pair<Integer,String> > getLessonsByDate(String data){
                ArrayList<Pair<Integer,String> > lessons = new ArrayList<Pair<Integer,String> >();
                try {
                        stmt = c.createStatement();

                        ResultSet rs = stmt.executeQuery("SELECT pl.id,k.oddzial,k.rok_rozpoczecia,p.nazwa,pl.nr_lekcji from plan_lekcji pl join przedmioty p on pl.id_przedmiotu = p.id join klasy k on p.id_klasy = k.id where pl.dzien_tygodnia=extract(dow from to_date('" + data + "', 'DD.MM.YYYY'))+1;");
                        while (rs.next()) {
                                Pair<Integer,String> pair = new Pair<Integer, String>(rs.getInt("id"),"godzina lekcyjna: "+rs.getInt("nr_lekcji") +" "+ rs.getString("nazwa") +" klasa: "+rs.getString("oddzial") + " " + rs.getInt("rok_rozpoczecia"));

                                //System.out.println(pair.getX()+" "+pair.getY());
                                lessons.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return lessons;
        }

        public ArrayList<Pair<String,String> > getStudentsByLesson(int lessonID){
                ArrayList<Pair<String,String> > students = new ArrayList<Pair<String,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,pesel from uczniowie u join klasy k on k.id =u.id_klasy join przedmioty p on p.id_klasy=k.id join plan_lekcji pl on pl.id_przedmiotu =p.id join przeprowadzone_lekcje p_l on pl.id=p_l.id_lekcji where p_l.id="+lessonID+";");
                        while (rs.next()) {
                                Pair<String,String> pair = new Pair<String, String>(rs.getString("pesel"),rs.getString("imie")+" "+rs.getString("nazwisko"));
//                                System.out.println(pair.getX()+" "+pair.getY());
                                students.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return students;
        }

        public ArrayList<Pair<Integer,String> > getAllClasses(){
                ArrayList<Pair<Integer,String> > classes = new ArrayList<Pair<Integer,String> >();
                try{
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("select * from klasy");
                        while(rs.next()){
                                classes.add(new Pair<Integer, String>(rs.getInt("id"),rs.getString("oddzial")+" "+rs.getString("rok_rozpoczecia")));
                        }
                        stmt.close();
                }catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return classes;
        }
        public ArrayList<Pair<String,String> > getAllStudents() {
                ArrayList<Pair<String,String> > students = new ArrayList<Pair<String,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,pesel from uczniowie where aktywny=true;");
                        while (rs.next()) {
                                Pair<String,String> pair = new Pair<String, String>(rs.getString("pesel"),rs.getString("imie")+" "+rs.getString("nazwisko"));
//                                System.out.println(pair.getX()+" "+pair.getY());
                                students.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return students;
        }
        public ArrayList<Pair<String,String> > getAllStudentsByAdmin() {
                ArrayList<Pair<String,String> > students = new ArrayList<Pair<String,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT * from uczniowie where aktywny=true;");
                        while (rs.next()) {
                                Pair<String,String> pair = new Pair<String, String>(rs.getString("pesel"),rs.getString("pesel")+" "+rs.getString("imie")+" "+rs.getString("nazwisko")+" "+rs.getString("telefon_do_rodzica"));
//                                System.out.println(pair.getX()+" "+pair.getY());
                                students.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return students;
        }

        public ArrayList<Pair<Integer,String> > getTeachersWithoutClass() {
                ArrayList<Pair<Integer,String> > teachers = new ArrayList<Pair<Integer,String> >();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,id from (SELECT imie,nazwisko,n.id,id_wychowawcy from nauczyciele n left join klasy k on n.id=k.id_wychowawcy) f where id_wychowawcy is null;");
                        while (rs.next()) {
                                Pair<Integer,String> pair = new Pair<Integer, String>(rs.getInt("id"),rs.getString("imie")+" "+rs.getString("nazwisko"));
                               // System.out.println(pair.getX()+" "+pair.getY());
                                teachers.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return teachers;
        }

        public ArrayList<Pair<Integer, String>> getAllTeachers() {
                ArrayList<Pair<Integer, String>> teachers = new ArrayList<Pair<Integer, String>>();
                try {
                        stmt = c.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT imie,nazwisko,id from nauczyciele");
                        while (rs.next()) {
                                Pair<Integer, String> pair = new Pair<Integer, String>(rs.getInt("id"), rs.getString("imie") + " " + rs.getString("nazwisko"));
                                // System.out.println(pair.getX()+" "+pair.getY());
                                teachers.add(pair);
                        }
//                        System.out.println("success");
                        rs.close();
                        stmt.close();
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return teachers;
        }
        public ArrayList<ArrayList<String> > getTeacherSchedule(int id){
                ArrayList<ArrayList<String> > shedule = new ArrayList<ArrayList<String>>();
                for(int i=0;i<5;i++){
                        shedule.add(new ArrayList<String>());
                }
                try {
                        for(int i=2;i<=6;i++){
                                stmt = c.createStatement();
                                ResultSet rs = stmt.executeQuery("select p.nazwa,pl.nr_lekcji,k.oddzial,k.rok_rozpoczecia from plan_lekcji pl join przedmioty p on pl.id_przedmiotu = p.id join klasy k on p.id_klasy = k.id join nauczyciele n on n.id=p.id_prowadzacego where dzien_tygodnia = "+i+" and n.id = '"+id+"' order by pl.nr_lekcji;");
                                int j=0;
                                while (rs.next()) {
                                        int tmp = rs.getInt("nr_lekcji");
                                        while(j<tmp){ shedule.get(i-2).add(""); j++;}
                                        shedule.get(i-2).add(rs.getString("nazwa")+"("+rs.getInt("rok_rozpoczecia")+rs.getString("oddzial")+")");
                                        j++;
                                }
                                while(shedule.get(i-2).size()<10) shedule.get(i-2).add("");
                                rs.close();
                                stmt.close();
                        }
//                        System.out.println("success");
                } catch (Exception e) {
                        e.printStackTrace();
                        System.err.println(e.getClass().getName() + ": " + e.getMessage());
                }
                return shedule;
        }


        public static void main(String args[])
        {
                DBManager dbManager = new DBManager();
                //String a = "to ja a to nie ja";
                //String b = "kurwa mac";
                //String c = "a owszem nie";
                //System.out.printf("%-20s %s",a,b+"\n");
                //System.out.printf("%-20s %s", b, c+"\n");
                //System.out.printf("%-20s %s",c,a+"\n");
                //dbManager.changeStudentPassword("95091673574","kamil");
               // dbManager.getTeachersWithoutClass();


                ArrayList<Pair<Integer,String> > sub = dbManager.getStudentSubjects("95091673574");
                for(int i=0;i<sub.size();i++) System.out.println(sub.get(i).getX()+" "+sub.get(i).getY());
        }
}
