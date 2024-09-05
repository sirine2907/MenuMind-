import 'package:flutter/material.dart';

class TypeOfProtectionScreen extends StatefulWidget {
  @override
  _TypeOfProtectionScreenState createState() => _TypeOfProtectionScreenState();
}

class _TypeOfProtectionScreenState extends State<TypeOfProtectionScreen> {
  final PageController _pageController = PageController(initialPage: 0);

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Type of Protection'),
        backgroundColor: Color(0xFFEB874A), // kPrimaryColor
      ),
      body: PageView(
        controller: _pageController,
        children: [
          _buildProtectionSection('assets/protection_1.png', 'Protection Type 1', 'Description of Protection Type 1'),
          _buildProtectionSection('assets/protection_2.png', 'Protection Type 2', 'Description of Protection Type 2'),
          // Add more sections as needed
        ],
      ),
    );
  }

  Widget _buildProtectionSection(String imagePath, String title, String description) {
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
              textAlign: TextAlign.center,
            ),
            SizedBox(height: 16),
            AspectRatio(
              aspectRatio: 3 / 2,
              child: Image.asset(
                imagePath,
                fit: BoxFit.contain,
              ),
            ),
            SizedBox(height: 16),
            Text(
              description,
              style: TextStyle(
                fontSize: 16,
              ),
              textAlign: TextAlign.justify,
            ),
          ],
        ),
      ),
    );
  }
}
