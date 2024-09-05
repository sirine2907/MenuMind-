import 'dart:convert';
import 'package:autrex/constants/icons.dart';
import 'package:autrex/constants/size.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_carousel_slider/carousel_slider.dart';
import 'package:http/http.dart' as http;



class Contact extends StatefulWidget {
  const Contact({super.key});

  @override
  State<Contact> createState() => _ContactState();
}

class _ContactState extends State<Contact> {
  int _selectedIndex = 0;
  final TextEditingController controllerName = TextEditingController();
  final TextEditingController controllerEmail = TextEditingController();
  final TextEditingController controllerSubject = TextEditingController();
  final TextEditingController controllerMessage = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  @override 
  void dispose() {
    controllerName.dispose();
    controllerEmail.dispose();
    controllerSubject.dispose();
    controllerMessage.dispose();
    super.dispose();
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }


  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Color(0xFFFFC850),
        appBar: AppBar(
          backgroundColor: Color(0xFFEB874A),
          title: const Text(
            "Contact Us",
            style: TextStyle(
                color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
          ),
        ),
        body: CarouselSlider(
          slideTransform: const CubeTransform(),
          slideIndicator: CircularSlideIndicator(
            padding: EdgeInsets.only(bottom: 50),
            currentIndicatorColor: Colors.white,
          ),
          unlimitedMode: true,
          children: [
            _buildInfoCard(),
            _buildFormCard(),
          ],
        ),
        bottomNavigationBar: BottomNavigationBar(
          type: BottomNavigationBarType.fixed,
          selectedItemColor: Color(0xffEB874A),
          backgroundColor: Colors.white,
          elevation: 0,
          items: [
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic11,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic1,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Home",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic22,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic2,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Contact Us",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic33,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic3,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Favorites",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic44,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic4,
                height: kBottomNavigationBarItemSize,
              ),
              label: "About Us",
            ),
          ],
          currentIndex: _selectedIndex,
          onTap: _onItemTapped,
        ),
      ),
    );
  }

  Widget _buildInfoCard() {
    return Container(
      color: Color(0xFFEB874A),
      child: Padding(
        padding: const EdgeInsets.only(top: 50, left: 40, bottom: 70),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[
            Row(
              children: <Widget>[
                CircleAvatar(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: const Image(
                      fit: BoxFit.cover,
                      image: AssetImage('assets/autris.jpeg'),
                    ),
                  ),
                ),
                const SizedBox(
                  width: 20,
                ),
                const Text(
                  "AUTRIS-Automation \n"
                  "&Intelligent Systems",
                  style: TextStyle(
                      color: Colors.white,
                      fontSize: 25,
                      fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const Column(
              children: [
                ListTile(
                  leading: Icon(
                    Icons.place_outlined,
                    color: Colors.white,
                  ),
                  title: Text(
                    "Boulogne-Billancourt\n"
                    " Île-de-France",
                    style: TextStyle(
                      fontSize: 20,
                      color: Colors.white,
                    ),
                  ),
                ),
                ListTile(
                  leading: Icon(
                    Icons.call_outlined,
                    color: Colors.white,
                  ),
                  title: Text(
                    "(020)67127777-\n"
                    "67127777/78/79/80/81\n"
                    ,
                    style: TextStyle(
                      fontSize: 20,
                      color: Colors.white,
                    ),
                  ),
                ),
                ListTile(
                  leading: Icon(
                    Icons.mail_outlined,
                    color: Colors.white,
                  ),
                  title: Text(
                    "autris@mail.com",
                    style: TextStyle(
                      fontSize: 20,
                      color: Colors.white,
                    ),
                  ),
                ),
                ListTile(
                  leading: Icon(
                    Icons.web_outlined,
                    color: Colors.white,
                  ),
                  title: Text(
                    "https://autris.fr/",
                    style: TextStyle(
                      fontSize: 20,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFormCard() {
    return Container(
      color: Color(0xFFFFC850),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _buildTextField(controllerName, "Name"),
              const SizedBox(height: 10),
              _buildTextField(controllerEmail, "Email",
                  keyboardType: TextInputType.emailAddress,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter an email';
                    }
                    if (!RegExp(r'\S+@\S+\.\S+').hasMatch(value)) {
                      return 'Please enter a valid email address';
                    }
                    return null;
                  }),
              const SizedBox(height: 10),
              _buildTextField(controllerSubject, "Subject"),
              const SizedBox(height: 10),
              _buildTextField(controllerMessage, "Message", maxLines: 3),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                      backgroundColor: Color(0xFFEB874A),), //color: Color(0xFFEB874A)
                  onPressed: () {
                    if (_formKey.currentState!.validate()) {
                      sendEmail(
                        name: controllerName.text,
                        email: controllerEmail.text,
                        subject: controllerSubject.text,
                        message: controllerMessage.text,
                      );
                    }
                  },
                  child: const Text(
                    "Send Email",
                    style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTextField(TextEditingController controller, String label,
      {TextInputType keyboardType = TextInputType.text, int maxLines = 1, String? Function(String?)? validator}) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(
          borderSide: BorderSide(),
        ),
      ),
      validator: validator ??
          (value) {
            if (value == null || value.isEmpty) {
              return 'Please enter $label';
            }
            return null;
          },
    );
  }

  Future sendEmail({
    required String name,
    required String email,
    required String subject,
    required String message,
  }) async {
    const serviceId = 'service_91kskss';
    const templateId = 'template_69ikz4a';
    const userId = 'q7PiCso02PDeS6gVY';

    final url = Uri.parse('https://api.emailjs.com/api/v1.0/email/send');
    final response = await http.post(
      url,
      headers: {
        'origin': 'http://localhost',
        'Content-Type': 'application/json',
      },
      body: json.encode(
        {
          'service_id': serviceId,
          'template_id': templateId,
          'user_id': userId,
          'template_params': {
            'user_name': name,
            'user_email': email,
            'user_subject': subject, 
            'user_message': message,
          },
        },
      ),
    );

    if (response.statusCode == 200) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Email sent successfully!')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Failed to send email. Please try again later.')),
      );
    }
  }
}
