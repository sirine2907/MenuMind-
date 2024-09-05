import 'package:flutter/material.dart';

class PosterScreen extends StatefulWidget {
  @override
  _PosterScreenState createState() => _PosterScreenState();
}

class _PosterScreenState extends State<PosterScreen> {
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
        title: Text('ATEX Poster'),
        backgroundColor: Color(0xFFEB874A), // kPrimaryColor
      ),
      body: PageView(
        controller: _pageController,
        children: [
          _buildPosterSection('assets/poster_1.png', 'Section 1 Title', 'Section 1 Description'),
          
        ],
      ),
    );
  }

  Widget _buildPosterSection(String imagePath, String title, String description) {
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
